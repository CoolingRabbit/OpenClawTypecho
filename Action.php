<?php
namespace TypechoPlugin\OpenClawTypecho;

use Typecho\Common;
use Typecho\Validate;
use Typecho\Widget\Exception;
use Widget\ActionInterface;
use Widget\Base\Contents;
use Widget\Base\Metas;
use Widget\Contents\EditTrait;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * OpenClaw Typecho Skill - API 处理器
 *
 * 处理 AI 服务通过 REST API 推送的文章，自动创建到 Typecho 博客。
 */
class Action extends Contents implements ActionInterface
{
    use EditTrait;

    /**
     * 获取主题字段 hook
     */
    protected function getThemeFieldsHook(): string
    {
        return '';
    }

    /**
     * 执行入口
     */
    public function execute()
    {
        // 不需要预查询数据
    }

    /**
     * 处理动作
     */
    public function action()
    {
        try {
            $this->handleRequest();
        } catch (\Exception $e) {
            $code = 400;
            if ($e->getMessage() === '鉴权失败') {
                $code = 401;
            }
            $this->sendError($e->getMessage(), $code);
        }
    }

    /**
     * 处理请求
     */
    protected function handleRequest()
    {
        if (!$this->request->isPost()) {
            throw new \Exception('只接受 POST 请求');
        }

        if (!$this->request->isJson()) {
            throw new \Exception('Content-Type 必须是 application/json');
        }

        $data = $this->request->get('@json');
        if (!is_array($data)) {
            throw new \Exception('JSON 格式无效');
        }

        $this->authenticate();

        $title = $this->sanitizeString($data['title'] ?? '');
        $text = $this->sanitizeText($data['text'] ?? '');
        $markdown = isset($data['markdown']) ? !empty($data['markdown']) : true;
        $category = $this->sanitizeString($data['category'] ?? '');
        $tags = $this->sanitizeTags($data['tags'] ?? []);
        $slug = $this->sanitizeSlug($data['slug'] ?? null);
        $requestedStatus = $this->sanitizeStatus($data['status'] ?? 'waiting');

        if (empty($title)) {
            throw new \Exception('标题不能为空');
        }

        if (empty($text)) {
            throw new \Exception('正文不能为空');
        }

        if (strlen($title) > 200) {
            throw new \Exception('标题长度不能超过 200 字符');
        }

        if (strlen($text) > 50000) {
            throw new \Exception('正文长度不能超过 50KB');
        }

        $this->checkSensitiveContent($title . $text);

        $config = Options::alloc()->plugin('OpenClawTypecho');
        $authorId = max(1, intval($config->authorId ?? 1));

        if ($markdown) {
            $text = '<!--markdown-->' . $text;
        }

        // 处理 draft 与 waiting 的状态映射
        $type = 'post';
        $dbStatus = $requestedStatus;
        if ($requestedStatus === 'draft') {
            $type = 'post_draft';
            $dbStatus = 'publish'; // 与 Typecho 草稿惯例一致
        }

        $contents = [
            'title'        => $title,
            'text'         => $text,
            'type'         => $type,
            'status'       => $dbStatus,
            'authorId'     => $authorId,
            'allowComment' => 1,
            'allowPing'    => 1,
            'allowFeed'    => 1,
        ];

        if ($slug) {
            $contents['slug'] = $slug;
        }

        $cid = $this->insert($contents);

        if ($cid <= 0) {
            throw new \Exception('创建文章失败');
        }

        $categoryName = $category ?: ($config->defaultCategory ?? 'AI知识库');
        $categoryId = $this->ensureCategory($categoryName);
        if ($categoryId) {
            $this->setCategories($cid, [$categoryId], false, false);
        }

        if (!empty($tags)) {
            $this->setTags($cid, implode(',', $tags), false, false);
        }

        $this->response->throwJson([
            'success' => true,
            'message' => '文章已保存',
            'cid'     => $cid,
            'status'  => $requestedStatus,
        ]);
    }

    /**
     * 鉴权
     */
    protected function authenticate()
    {
        $auth = $this->request->getHeader('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $matches)) {
            throw new \Exception('鉴权失败');
        }

        $token = $matches[1];
        $config = Options::alloc()->plugin('OpenClawTypecho');
        $expectedToken = $config->token ?? '';

        if (empty($expectedToken) || empty($token) || !hash_equals($expectedToken, $token)) {
            throw new \Exception('鉴权失败');
        }
    }

    /**
     * 字符串清理
     */
    protected function sanitizeString(?string $value): string
    {
        return trim($value ?? '');
    }

    /**
     * 正文清理
     */
    protected function sanitizeText(?string $value): string
    {
        return trim($value ?? '');
    }

    /**
     * 标签清理
     */
    protected function sanitizeTags($tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }
        if (!is_array($tags)) {
            return [];
        }

        $result = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (strlen($tag) > 0 && strlen($tag) <= 100 && Validate::xssCheck($tag)) {
                $result[] = $tag;
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * 缩略名清理
     */
    protected function sanitizeSlug(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^[a-zA-Z0-9\-_]+$/', $value) && strlen($value) <= 200) {
            return $value;
        }
        return null;
    }

    /**
     * 状态强制约束
     */
    protected function sanitizeStatus(?string $value): string
    {
        $allowed = ['waiting', 'draft', 'private', 'hidden'];
        if (in_array($value, $allowed, true)) {
            return $value;
        }
        return 'waiting';
    }

    /**
     * 确保分类存在
     */
    protected function ensureCategory(string $name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $row = $this->db->fetchRow(
            $this->db->select('mid')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->where('name = ?', $name)
                ->limit(1)
        );

        if ($row) {
            return intval($row['mid']);
        }

        $slug = Common::slugName($name) ?: 'uncategorized';

        $mid = Metas::alloc()->insert([
            'name'        => $name,
            'slug'        => $slug,
            'type'        => 'category',
            'count'       => 0,
            'order'       => 0,
            'parent'      => 0,
            'description' => '',
        ]);

        return $mid > 0 ? $mid : null;
    }

    /**
     * 敏感内容检查
     */
    protected function checkSensitiveContent(string $content): void
    {
        $patterns = [
            '手机号'    => '/1[3-9]\d{9}/',
            '身份证号'  => '/\d{17}[\dXx]|\d{15}/',
            '银行卡号'  => '/\d{16,19}/',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $content)) {
                throw new \Exception("内容疑似包含{$name}等敏感信息，请人工确认后提交");
            }
        }
    }

    /**
     * 返回错误
     */
    protected function sendError(string $message, int $code = 400)
    {
        $this->response->setStatus($code);
        $this->response->throwJson([
            'success' => false,
            'message' => $message,
        ]);
    }
}
