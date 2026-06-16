# OpenClawTypecho

> **OpenClaw Typecho Skill** — 最轻量的 AI 知识库方案。
>
> 一台廉价的 PHP 虚拟主机 + 一个 Typecho 博客 + 这个插件 = **AI 直接管理的在线知识库**。装完插件，把 Skill 文件丢给 AI，即可开始归档、检索、更新文章。无需维护索引文件，无需配置目录规则，无需担心 Token 暴雷。
>
> **与 Obsidian AI 知识库的区别？**
> | | OpenClawTypecho | Obsidian + AI |
> |---|---|---|
> | **成本** | 虚拟主机 ¥50-100/年 | 本地免费，但 AI 插件/API 额外计费 |
> | **配置** | 装插件 → 配置 Token → 完事 | 需维护 CLAUDE.md、index.md、目录结构 |
> | **访问** | 天生在线，URL 即可分享 | 本地优先，分享需配同步 |
> | **AI 操作** | API 直写数据库，无 Token 压力 | AI 需读取大量 Markdown，上下文易爆炸 |
> | **适用** | 快速归档、博客发布、轻量知识库 | 深度研究、复杂图谱、本地编译 |
>
> 如果你需要的是**随手丢给 AI 就能自动归档、随时在线查看**的知识库，而非一套需要精心维护的本地维基系统——这个方案就是为你准备的。

---

## 功能

- 为 OpenClaw 等 AI 服务提供 REST API 端点
- **完整的文章 CRUD**：创建、查询列表、查询详情、更新、删除
- 自动创建分类、标签
- Token 鉴权 + 敏感内容过滤
- 文章状态控制：待审核 / 草稿 / 私密 / 隐藏

---

## 使用方式

### 第一步：安装插件到 Typecho

1. 将 `Plugin.php` 和 `Action.php` 上传到 Typecho 服务器 `usr/plugins/OpenClawTypecho/`
2. 后台 → 插件 → 启用 **OpenClawTypecho** → 进入设置

### 配置项

| 配置项 | 说明 |
|--------|------|
| **API 访问密钥 (Token)** | 点击"🔑 自动生成"按钮生成，AI 调用时携带 `Authorization: Bearer <token>` |
| **AI 文章归属作者** | 下拉框选择用户。建议创建专用用户 `ai` |
| **默认文章分类** | 如 `AI知识库`。不存在时自动创建 |

### 第二步：将 Skill 提交给 AI Agent

把 **`OpenClaw Typecho Skill.md`** 提交给你的 AI Agent（如 OpenClaw、ChatGPT、Kimi 等），Agent 会自动完成 Skill 安装和配置。

配置一次后，AI 即可直接管理您的博客文章，无需重复询问。

---

## API 端点

```
POST https://your-blog.com/index.php/action/openclaw-submit
```

请求头：
```
Content-Type: application/json
Authorization: Bearer <token>
```

### 公共字段

每个请求都需包含 `action` 字段指定操作类型：

| action | 说明 |
|--------|------|
| `submit` | 创建文章 |
| `list` | 查询文章列表 |
| `get` | 查询单篇文章 |
| `update` | 更新文章 |
| `delete` | 删除文章 |

---

### 1. 创建文章 — `submit`

| 字段 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| `action` | string | ✅ | — | 固定值 `submit` |
| `title` | string | ✅ | — | 文章标题 |
| `text` | string | ✅ | — | 文章正文 |
| `markdown` | boolean | 否 | `true` | 是否 Markdown 格式 |
| `category` | string | 否 | 插件设置 | 分类名称，不存在时自动创建 |
| `tags` | array | 否 | `[]` | 标签数组 |
| `slug` | string | 否 | 自动生成 | URL 缩略名 |
| `status` | string | 否 | `waiting` | 文章状态 |

**请求示例：**
```json
{
  "action": "submit",
  "title": "我的第一篇 AI 文章",
  "text": "## 正文内容\n\n支持 Markdown 格式",
  "category": "AI知识库",
  "tags": ["AI", "Typecho"],
  "status": "waiting"
}
```

**响应示例：**
```json
{
  "success": true,
  "message": "文章已创建",
  "cid": 42,
  "status": "waiting",
  "action": "submit"
}
```

---

### 2. 查询列表 — `list`

| 字段 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| `action` | string | ✅ | — | 固定值 `list` |
| `page` | int | 否 | `1` | 页码 |
| `pageSize` | int | 否 | `10` | 每页数量，最大 50 |
| `status` | string | 否 | — | 按状态过滤 |
| `category` | string | 否 | — | 按分类过滤 |

**请求示例：**
```json
{
  "action": "list",
  "page": 1,
  "pageSize": 10,
  "status": "waiting"
}
```

**响应示例：**
```json
{
  "success": true,
  "action": "list",
  "page": 1,
  "pageSize": 10,
  "total": 25,
  "totalPage": 3,
  "data": [
    {
      "cid": 42,
      "title": "我的第一篇 AI 文章",
      "slug": "my-first-ai-article",
      "created": "2026-06-16 14:30:00",
      "modified": "2026-06-16 14:30:00",
      "status": "waiting",
      "authorId": 2,
      "authorName": "AI助手",
      "categories": [{"name": "AI知识库", "slug": "ai-knowledge"}],
      "tags": ["AI", "Typecho"]
    }
  ]
}
```

---

### 3. 查询单篇 — `get`

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | ✅ | 固定值 `get` |
| `cid` | int | ✅ | 文章 ID |

**请求示例：**
```json
{
  "action": "get",
  "cid": 42
}
```

**响应示例：**
```json
{
  "success": true,
  "action": "get",
  "data": {
    "cid": 42,
    "title": "我的第一篇 AI 文章",
    "slug": "my-first-ai-article",
    "text": "## 正文内容\n\n支持 Markdown 格式",
    "created": "2026-06-16 14:30:00",
    "modified": "2026-06-16 14:30:00",
    "status": "waiting",
    "authorId": 2,
    "isMarkdown": true,
    "allowComment": 1,
    "allowPing": 1,
    "allowFeed": 1,
    "categories": [{"name": "AI知识库", "slug": "ai-knowledge"}],
    "tags": ["AI", "Typecho"]
  }
}
```

---

### 4. 更新文章 — `update`

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | ✅ | 固定值 `update` |
| `cid` | int | ✅ | 文章 ID |
| `title` | string | 否 | 新标题 |
| `text` | string | 否 | 新正文 |
| `markdown` | boolean | 否 | 是否 Markdown，默认 `true` |
| `category` | string | 否 | 新分类 |
| `tags` | array | 否 | 新标签 |
| `slug` | string | 否 | 新缩略名 |
| `status` | string | 否 | 新状态 |

**请求示例：**
```json
{
  "action": "update",
  "cid": 42,
  "title": "更新后的标题",
  "status": "publish"
}
```

**响应示例：**
```json
{
  "success": true,
  "message": "文章已更新",
  "cid": 42,
  "action": "update"
}
```

---

### 5. 删除文章 — `delete`

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | ✅ | 固定值 `delete` |
| `cid` | int | ✅ | 文章 ID |

**请求示例：**
```json
{
  "action": "delete",
  "cid": 42
}
```

**响应示例：**
```json
{
  "success": true,
  "message": "文章已删除",
  "cid": 42,
  "action": "delete"
}
```

---

## 状态选项

| 状态 | 说明 | 适用场景 |
|------|------|---------|
| `waiting` | 待审核，后台手动发布 | **默认推荐**，适合内容审核 |
| `draft` | 草稿 | 暂不公开 |
| `private` | 私密 | 仅作者可见 |
| `hidden` | 隐藏 | 可通过 URL 访问但不在列表中 |

---

## 安全机制

- Bearer Token 鉴权
- 只接受 POST + JSON
- 敏感内容过滤（手机号、身份证、银行卡号自动拦截）
- XSS 过滤
- 标题 ≤ 200 字符，正文 ≤ 50KB

---

## 错误响应

所有错误统一返回格式：

```json
{
  "success": false,
  "message": "错误描述"
}
```

HTTP 状态码：
- `400` — 请求参数错误
- `401` — 鉴权失败

---



## 环境要求

- Typecho ≥ 1.2.0
- PHP ≥ 7.4

---

## 更新日志

### v1.1.0
- 新增文章查询列表（`list`）
- 新增单篇文章查询（`get`）
- 新增文章更新（`update`）
- 新增文章删除（`delete`）
- 统一单入口路由，通过 `action` 字段分发

### v1.0.0
- 初始版本：支持文章创建（`submit`）

---

## 许可证

GPL-3.0
