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
 * 为 OpenClaw 等 AI 服务提供 REST API，支持文章的创建、查询、更新、删除。
 *
 * @version 2.0.0
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
     * 处理动作（统一入口）
     */
    public function action()
    {
        try {
            $this->handleRequest();
        } catch (\Exception $e) {
            $code = 400;
            if (strpos($e->getMessage(), '鉴权失败') === 0) {
                $code = 401;
            }
            $this->sendError($e->getMessage(), $code);
        }
    }

    /**
     * 处理请求（路由分发）
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

        $action = $data['action'] ?? 'submit';
        $allowedActions = ['submit', 'list', 'get', 'update', 'delete'];

        if (!in_array($action, $allowedActions, true)) {
            throw new \Exception('无效的操作类型，允许的值为: ' . implode(', ', $allowedActions));
        }

        $method = 'handle' . ucfirst($action);
        $this->$method($data);
    }

    // ==================== 创建文章 ====================

    /**
     * 处理创建文章
     */
    protected function handleSubmit(array $data)
    {
        $title = $this->sanitizeString($data['title'] ?? '');
        $text = $this->sanitizeText($data['text'] ?? '');
        $markdown = isset($data['markdown']) ? !empty($data['markdown']) : true;
        $category = $this->sanitizeString($data['category'] ?? '');
        $tags = $this->sanitizeTags($data['tags'] ?? []);
        $slug = $this->sanitizeSlug($data['slug'] ?? null);
        $requestedStatus = $this->sanitizeStatus($data['status'] ?? 'waiting');

        $this->validateArticleInput($title, $text);

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
            $dbStatus = 'publish';
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
            throw new \Exception('创建文章失败：数据库写入异常，请检查数据库权限和表结构');
        }

        $categoryName = $category;
        if (!empty($categoryName)) {
            $categoryId = $this->ensureCategory($categoryName);
            if ($categoryId) {
                $this->setCategories($cid, [$categoryId], false, false);
            }
        }

        if (!empty($tags)) {
            $this->setTags($cid, implode(',', $tags), false, false);
        }

        $this->response->throwJson([
            'success' => true,
            'message' => '文章已创建',
            'cid'     => $cid,
            'status'  => $requestedStatus,
            'action'  => 'submit',
        ]);
    }

    // ==================== 查询列表 ====================

    /**
     * 处理文章列表查询
     */
    protected function handleList(array $data)
    {
        $page = max(1, intval($data['page'] ?? 1));
        $pageSize = min(50, max(1, intval($data['pageSize'] ?? 10)));
        $statusFilter = $data['status'] ?? null;
        $categoryFilter = $this->sanitizeString($data['category'] ?? '');

        $offset = ($page - 1) * $pageSize;

        // 构建基础查询
        $select = $this->db->select(
                'c.cid',
                'c.title',
                'c.slug',
                'c.created',
                'c.modified',
                'c.status',
                'c.type',
                'c.authorId',
                'u.screenName as authorName'
            )
            ->from('table.contents as c')
            ->join('table.users as u', 'c.authorId = u.uid')
            ->where('c.type = ? OR c.type = ?', 'post', 'post_draft')
            ->order('c.created', \Typecho\Db::SORT_DESC)
            ->offset($offset)
            ->limit($pageSize);

        // 状态过滤
        if ($statusFilter !== null) {
            $dbStatus = $this->mapStatusToDb($statusFilter);
            if ($dbStatus !== null) {
                $select->where('c.status = ?', $dbStatus);
                if ($statusFilter === 'draft') {
                    $select->where('c.type = ?', 'post_draft');
                } else {
                    $select->where('c.type = ?', 'post');
                }
            }
        }

        $articles = $this->db->fetchAll($select);

        // 查询分类信息
        foreach ($articles as &$article) {
            $article['created'] = date('Y-m-d H:i:s', $article['created']);
            $article['modified'] = date('Y-m-d H:i:s', $article['modified']);
            $article['status'] = $this->mapDbStatusToLabel($article['status'], $article['type']);
            $article['categories'] = $this->getCategoriesByCid($article['cid']);
            $article['tags'] = $this->getTagsByCid($article['cid']);
            unset($article['type']);
        }

        // 统计总数
        $countSelect = $this->db->select('COUNT(*) as total')
            ->from('table.contents')
            ->where('type = ? OR type = ?', 'post', 'post_draft');
        if ($statusFilter !== null) {
            $dbStatus = $this->mapStatusToDb($statusFilter);
            if ($dbStatus !== null) {
                $countSelect->where('status = ?', $dbStatus);
            }
        }
        $total = $this->db->fetchRow($countSelect)['total'] ?? 0;

        $this->response->throwJson([
            'success'   => true,
            'action'    => 'list',
            'page'      => $page,
            'pageSize'  => $pageSize,
            'total'     => intval($total),
            'totalPage' => ceil(intval($total) / $pageSize),
            'data'      => $articles,
        ]);
    }

    // ==================== 查询单篇 ====================

    /**
     * 处理单篇文章查询
     */
    protected function handleGet(array $data)
    {
        $cid = intval($data['cid'] ?? 0);
        if ($cid <= 0) {
            throw new \Exception('缺少 cid 参数');
        }

        $article = $this->db->fetchRow(
            $this->db->select(
                'cid',
                'title',
                'slug',
                'text',
                'created',
                'modified',
                'status',
                'type',
                'authorId',
                'allowComment',
                'allowPing',
                'allowFeed'
            )
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->limit(1)
        );

        if (!$article) {
            throw new \Exception('文章不存在：cid ' . $cid . ' 对应的文章未找到');
        }

        $article['created'] = date('Y-m-d H:i:s', $article['created']);
        $article['modified'] = date('Y-m-d H:i:s', $article['modified']);
        $article['status'] = $this->mapDbStatusToLabel($article['status'], $article['type']);
        $article['isMarkdown'] = strpos($article['text'], '<!--markdown-->') === 0;
        if ($article['isMarkdown']) {
            $article['text'] = substr($article['text'], strlen('<!--markdown-->'));
        }
        $article['categories'] = $this->getCategoriesByCid($cid);
        $article['tags'] = $this->getTagsByCid($cid);
        unset($article['type']);

        $this->response->throwJson([
            'success' => true,
            'action'  => 'get',
            'data'    => $article,
        ]);
    }

    // ==================== 更新文章 ====================

    /**
     * 处理文章更新
     */
    protected function handleUpdate(array $data)
    {
        $cid = intval($data['cid'] ?? 0);
        if ($cid <= 0) {
            throw new \Exception('缺少 cid 参数');
        }

        // 检查文章是否存在
        $exists = $this->db->fetchRow(
            $this->db->select('cid', 'type', 'status')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->limit(1)
        );

        if (!$exists) {
            throw new \Exception('文章不存在：cid ' . $cid . ' 对应的文章未找到');
        }

        $update = [];

        // 标题
        if (isset($data['title'])) {
            $title = $this->sanitizeString($data['title']);
            if (empty($title)) {
                throw new \Exception('标题不能为空');
            }
            if (strlen($title) > 200) {
                throw new \Exception('标题长度不能超过 200 字符');
            }
            $update['title'] = $title;
        }

        // 正文
        if (isset($data['text'])) {
            $text = $this->sanitizeText($data['text']);
            if (isset($data['title']) && empty($text)) {
                throw new \Exception('正文不能为空');
            }
            if (strlen($text) > 50000) {
                throw new \Exception('正文长度不能超过 50KB');
            }

            $markdown = isset($data['markdown']) ? !empty($data['markdown']) : true;
            if ($markdown) {
                $text = '<!--markdown-->' . $text;
            }
            $update['text'] = $text;
        }

        // 缩略名
        if (array_key_exists('slug', $data)) {
            $slug = $this->sanitizeSlug($data['slug']);
            if ($slug !== null) {
                $update['slug'] = $slug;
            }
        }

        // 状态
        if (isset($data['status'])) {
            $requestedStatus = $this->sanitizeStatus($data['status']);
            if ($requestedStatus === 'draft') {
                $update['type'] = 'post_draft';
                $update['status'] = 'publish';
            } else {
                $update['type'] = 'post';
                $update['status'] = $requestedStatus;
            }
        }

        if (empty($update)) {
            throw new \Exception('没有需要更新的字段：请至少传入 title、text、category、tags、slug 或 status 中的一个');
        }

        $update['modified'] = time();

        // 敏感内容检查
        $checkContent = ($update['title'] ?? '') . ($update['text'] ?? '');
        if (!empty($checkContent)) {
            $this->checkSensitiveContent($checkContent);
        }

        // 执行更新
        $this->db->query(
            $this->db->update('table.contents')
                ->rows($update)
                ->where('cid = ?', $cid)
        );

        // 更新分类
        if (isset($data['category'])) {
            $category = $this->sanitizeString($data['category']);
            if (!empty($category)) {
                $categoryId = $this->ensureCategory($category);
                if ($categoryId) {
                    $this->setCategories($cid, [$categoryId], false, false);
                }
            }
        }

        // 更新标签
        if (isset($data['tags'])) {
            $tags = $this->sanitizeTags($data['tags']);
            $this->setTags($cid, implode(',', $tags), false, false);
        }

        // 更新计数
        $this->updateCategoryCount();

        $this->response->throwJson([
            'success' => true,
            'message' => '文章已更新',
            'cid'     => $cid,
            'action'  => 'update',
        ]);
    }

    // ==================== 删除文章 ====================

    /**
     * 处理文章删除
     */
    protected function handleDelete(array $data)
    {
        $cid = intval($data['cid'] ?? 0);
        if ($cid <= 0) {
            throw new \Exception('缺少 cid 参数');
        }

        // 检查文章是否存在
        $exists = $this->db->fetchRow(
            $this->db->select('cid')
                ->from('table.contents')
                ->where('cid = ?', $cid)
                ->limit(1)
        );

        if (!$exists) {
            throw new \Exception('文章不存在：cid ' . $cid . ' 对应的文章未找到');
        }

        // 删除关联的分类和标签关系
        $this->db->query(
            $this->db->delete('table.relationships')
                ->where('cid = ?', $cid)
        );

        // 删除文章
        $this->db->query(
            $this->db->delete('table.contents')
                ->where('cid = ?', $cid)
        );

        // 更新分类计数
        $this->updateCategoryCount();

        $this->response->throwJson([
            'success' => true,
            'message' => '文章已删除',
            'cid'     => $cid,
            'action'  => 'delete',
        ]);
    }

    // ==================== 辅助方法 ====================

    /**
     * 验证文章输入
     */
    protected function validateArticleInput(string $title, string $text): void
    {
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
    }

    /**
     * 获取文章的分类
     */
    protected function getCategoriesByCid(int $cid): array
    {
        $rows = $this->db->fetchAll(
            $this->db->select('m.name', 'm.slug')
                ->from('table.metas as m')
                ->join('table.relationships as r', 'm.mid = r.mid')
                ->where('r.cid = ?', $cid)
                ->where('m.type = ?', 'category')
        );

        return array_map(function ($row) {
            return ['name' => $row['name'], 'slug' => $row['slug']];
        }, $rows);
    }

    /**
     * 获取文章的标签
     */
    protected function getTagsByCid(int $cid): array
    {
        $rows = $this->db->fetchAll(
            $this->db->select('m.name', 'm.slug')
                ->from('table.metas as m')
                ->join('table.relationships as r', 'm.mid = r.mid')
                ->where('r.cid = ?', $cid)
                ->where('m.type = ?', 'tag')
        );

        return array_map(function ($row) {
            return $row['name'];
        }, $rows);
    }

    /**
     * 状态映射：前端标签 → 数据库存储
     */
    protected function mapStatusToDb(string $status): ?string
    {
        switch ($status) {
            case 'waiting': return 'waiting';
            case 'draft':   return 'publish';
            case 'publish': return 'publish';
            case 'private': return 'private';
            case 'hidden':  return 'hidden';
            default:        return null;
        }
    }

    /**
     * 状态映射：数据库存储 → 前端标签
     */
    protected function mapDbStatusToLabel(string $dbStatus, string $type): string
    {
        if ($type === 'post_draft') {
            return 'draft';
        }
        return $dbStatus;
    }

    /**
     * 鉴权
     */
    protected function authenticate()
    {
        $auth = $this->request->getHeader('Authorization', '');
        if (empty($auth)) {
            throw new \Exception('鉴权失败：请求缺少 Authorization 头');
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $matches)) {
            throw new \Exception('鉴权失败：Authorization 格式错误，应为 Bearer <token>');
        }

        $token = $matches[1];
        $config = Options::alloc()->plugin('OpenClawTypecho');
        $expectedToken = $config->token ?? '';

        if (empty($expectedToken)) {
            throw new \Exception('鉴权失败：插件未配置 Token，请进入后台 → 插件 → OpenClawTypecho → 设置生成 Token');
        }
        if (empty($token) || !hash_equals($expectedToken, $token)) {
            throw new \Exception('鉴权失败：Token 无效或已过期');
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
     * 状态约束——保留 draft 特殊处理，其余原样透传
     */
    protected function sanitizeStatus(?string $value): string
    {
        $value = trim($value ?? '');
        return $value !== '' ? $value : 'waiting';
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
