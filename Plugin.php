<?php
namespace TypechoPlugin\OpenClawTypecho;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Select;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * OpenClaw Typecho Skill
 *
 * 为 OpenClaw 等 AI 服务提供 REST API，支持向 Typecho 博客创建、查询、更新、删除文章，构建 AI 知识库。
 *
 * @package OpenClawTypecho
 * @author CoolingRabbit
 * @version 1.1.0
 * @link https://github.com/CoolingRabbit/OpenClawTypecho
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件
     */
    public static function activate()
    {
        Helper::addAction('openclaw-submit', '\TypechoPlugin\OpenClawTypecho\Action');
        return _t('插件已激活，请进入设置进行配置');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        Helper::removeAction('openclaw-submit');
    }

    /**
     * 配置面板
     */
    public static function config(Form $form)
    {
        // 1. API Token - 改为 Text 类型方便查看，加 JS 自动生成按钮
        $token = new Text('token', null, null, 
            _t('API 访问密钥 (Token)'), 
            _t('AI 服务调用本接口时，必须在请求头中携带：Authorization: Bearer <token>。<br>点击下方按钮可自动生成随机 Token。')
        );
        $token->addRule('required', _t('Token 不能为空'));
        $form->addInput($token);

        // 2. 作者用户ID - 改为下拉框，从用户列表读取
        $db = \Typecho\Db::get();
        $users = $db->fetchAll(
            $db->select('uid', 'screenName', 'name')
                ->from('table.users')
                ->order('uid', \Typecho\Db::SORT_ASC)
        );
        
        $userOptions = [];
        foreach ($users as $user) {
            $displayName = !empty($user['screenName']) ? $user['screenName'] : $user['name'];
            $userOptions[$user['uid']] = $displayName . ' (ID: ' . $user['uid'] . ')';
        }
        
        $authorId = new Select('authorId', $userOptions, '1', 
            _t('AI 文章归属作者'), 
            _t('选择 AI 发布文章时所使用的作者账户。建议专门创建一个用户（如用户名 "ai"），用户组设为"贡献者"，以区分人工文章和 AI 文章。')
        );
        $form->addInput($authorId);

        // 3. 默认分类
        $defaultCategory = new Text('defaultCategory', null, 'AI知识库', 
            _t('默认文章分类'), 
            _t('当 AI 推送文章时没有指定分类，则自动归入此分类。若该分类不存在，插件会自动创建。')
        );
        $form->addInput($defaultCategory);

        // 添加 Token 自动生成按钮的 JS
        $script = new \Typecho\Widget\Helper\Layout('script');
        $script->setAttribute('type', 'text/javascript');
        $script->html("
        (function() {
            function generateOpenClawToken() {
                var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                var token = '';
                for (var i = 0; i < 48; i++) {
                    token += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                var input = document.querySelector('input[name=\"token\"]');
                if (input) {
                    input.value = token;
                    input.type = 'text';
                    input.select();
                    try {
                        document.execCommand('copy');
                    } catch(e) {}
                    
                    var btn = document.querySelector('#openclaw-generate-btn');
                    if (btn) {
                        btn.innerHTML = '✅ 已生成并填入';
                        btn.style.background = '#27C93F';
                        setTimeout(function() {
                            btn.innerHTML = '🔑 自动生成随机 Token';
                            btn.style.background = '#467CFD';
                        }, 2000);
                    }
                }
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                var tokenInput = document.querySelector('input[name=\"token\"]');
                if (tokenInput) {
                    var btn = document.createElement('button');
                    btn.id = 'openclaw-generate-btn';
                    btn.type = 'button';
                    btn.style.cssText = 'margin-top:6px;padding:4px 10px;background:#467CFD;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:12px;';
                    btn.innerHTML = '🔑 自动生成随机 Token';
                    btn.onclick = generateOpenClawToken;
                    
                    tokenInput.parentNode.insertBefore(btn, tokenInput.nextSibling);
                }
            });
        })();
        ");
        $form->addItem($script);
    }

    /**
     * 个人配置
     */
    public static function personalConfig(Form $form)
    {
    }
}
