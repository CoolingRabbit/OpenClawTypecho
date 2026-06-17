---
name: openclaw-typecho-skill
description: AI 直接管理的 Typecho 博客知识库技能 — 创建、查询、更新、删除文章
version: 1.1.1
---

# OpenClaw Typecho Skill

> **用途**：当 AI 需要将生成的内容保存到 Typecho 博客，或管理已有文章时，按此流程操作。

---

## 前置条件

### 需要 Typecho 博客

本技能需要用户已有 Typecho 博客，并安装了 [OpenClawTypecho 插件](https://github.com/CoolingRabbit/OpenClawTypecho)。

**简要部署步骤：**
1. 准备一台支持 PHP 8.0+ 和 MySQL 的服务器
2. 安装 Typecho 博客程序
3. 下载 [OpenClawTypecho 插件](https://github.com/CoolingRabbit/OpenClawTypecho/releases) 并启用
4. 在插件设置中生成 API Token

**完整部署教程：** 参见 GitHub 仓库 → https://github.com/CoolingRabbit/OpenClawTypecho

---

## 配置说明

本 Skill 通过本地配置持久化博客地址和 Token，配置一次后无需重复询问。

### 配置项

| 配置项 | 必填 | 说明 | 示例 |
|--------|------|------|------|
| `domain` | ✅ | Typecho 博客地址 | `https://www.example.com` |
| `token` | ✅ | OpenClawTypecho 插件的 API Token | `sk-live-xxx` |
| `default_category` | 否 | 默认分类 | `AI知识库` |
| `default_tags` | 否 | 默认标签数组 | `["笔记", "Typecho"]` |

### 配置命令

**AI 根据自己所处的平台，选择合适的方式将以下配置持久化：**

```
domain=<博客地址>
token=<Token>
default_category=<分类>    (可选)
default_tags=<标签1,标签2>  (可选)
```

**AI 应引导用户完成配置，例如：**
> 您还没有配置 Typecho 博客信息，请配置以下参数：
> - `domain`: 你的博客地址，如 `https://www.example.com`
> - `token`: 插件 API Token
>
> 请根据你的 AI 平台配置方式完成设置。

### 配置获取方式

- **博客地址**：你的 Typecho 博客 URL，如 `https://www.example.com`
- **API Token**：登录博客后台 → 插件 → OpenClawTypecho → 设置 → 点击"🔑 自动生成随机 Token" → 复制

---

## 触发条件

### 发布/保存类
- "发文章到博客" / "保存到博客"
- "AI 知识库归档" / "归档到博客"
- "Typecho 发布" / "自动发布"
- "保存这篇笔记"
- "生成博客文章"

### 查询类
- "查看博客文章列表"
- "我的文章有哪些"
- "查一下知识库里的文章"
- "cid 为 XX 的文章内容"

### 管理类
- "更新/修改这篇文章"
- "删除博客里的某篇文章"
- "改一下文章的分类/标签"

---

## API 概述

所有操作通过统一端点，在请求体中用 `action` 字段区分：

```
POST {config.domain}/index.php/action/openclaw-submit
Authorization: Bearer {config.token}
Content-Type: application/json
```

| action | 说明 |
|--------|------|
| `submit` | 创建文章 |
| `list` | 查询文章列表 |
| `get` | 查询单篇文章 |
| `update` | 更新文章 |
| `delete` | 删除文章 |

---

## 1. 创建文章 — `submit`

### 触发场景
用户要求保存内容到博客。

### 第 1 步：读取配置
- 若 `domain` 和 `token` 均已配置 → 继续
- 若任一未配置 → **提示用户配置，不继续执行**

### 第 2 步：确认分类和标签（可选）
AI 询问用户是否使用默认值，或让用户自定义。

### 第 3 步：调用 API

**请求参数：**

| 字段 | 类型 | 必填 | 说明 | 默认值 |
|------|------|------|------|--------|
| `action` | string | ✅ | 固定值 `submit` | — |
| `title` | string | ✅ | 文章标题 | — |
| `text` | string | ✅ | 文章正文（Markdown） | — |
| `markdown` | boolean | 否 | 是否 Markdown | `true` |
| `category` | string | 否 | 分类名称 | `config.default_category` |
| `tags` | array | 否 | 标签数组 | `config.default_tags` |
| `slug` | string | 否 | URL 缩略名 | 自动生成 |
| `status` | string | 否 | 文章状态 | `waiting` |

**状态选项：**
| 状态 | 使用场景 |
|------|---------|
| `waiting` | **默认推荐**。待审核，需后台手动发布。 |
| `draft` | 草稿 |
| `private` | 私密 |
| `hidden` | 隐藏 |

**请求示例：**
```json
{
  "action": "submit",
  "title": "Typecho 博客 HTTPS 配置踩坑记录",
  "text": "## 问题描述\n\n配置 SSL 证书后...",
  "category": "技术笔记",
  "tags": ["Typecho", "SSL", "踩坑"],
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

## 2. 查询列表 — `list`

### 触发场景
用户想查看博客里的文章清单。

### 请求参数

| 字段 | 类型 | 必填 | 说明 | 默认值 |
|------|------|------|------|--------|
| `action` | string | ✅ | 固定值 `list` | — |
| `page` | int | 否 | 页码 | `1` |
| `pageSize` | int | 否 | 每页数量（最大 50） | `10` |
| `status` | string | 否 | 按状态过滤 | — |
| `category` | string | 否 | 按分类过滤 | — |

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
      "title": "文章标题",
      "slug": "article-slug",
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

## 3. 查询单篇 — `get`

### 触发场景
用户想查看某篇特定文章的内容。

### 请求参数

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
    "title": "文章标题",
    "slug": "article-slug",
    "text": "文章正文内容...",
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

## 4. 更新文章 — `update`

### 触发场景
用户要求修改已有文章的内容、分类、标签或状态。

### 请求参数

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

**注意：** 只需传入需要修改的字段，未传入的字段保持原值。

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

## 5. 删除文章 — `delete`

### 触发场景
用户要求删除某篇文章。

### 请求参数

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

## 正文格式规范

使用 Markdown 格式，插件会自动加上 `<!--markdown-->` 前缀：

```markdown
## 引言
这是文章的第一段...

## 核心内容
- 要点 1
- 要点 2

## 总结
...
```

**正文应包含：**
- 清晰的 H2/H3 标题层级
- 代码块用 ` ``` ` 包裹
- 表格用 Markdown 表格语法
- 图片用 `![描述](完整URL)` 引用（外链）

---

## 标签和分类建议

**分类：**
- `AI知识库` — AI 工具、技巧
- `技术笔记` — 编程、开发、运维
- `随笔` — 日常思考
- `教程` — 步骤讲解

**标签（3-5 个）：**
- 技术：`Python`, `Typecho`, `Nginx`, `SSL`, `Docker`
- AI：`LLM`, `OpenClaw`, `Prompt`, `RAG`
- 通用：`笔记`, `备忘`, `教程`, `踩坑`

---

## 响应处理

### 成功响应
所有成功响应都包含 `success: true` 和对应的 `action` 字段。

### 错误响应
```json
{
  "success": false,
  "message": "错误描述"
}
```

**HTTP 状态码：**
- `400` — 请求参数错误
- `401` — 鉴权失败

**常见错误：**
- `401 鉴权失败` — Token 错误或过期，告知用户重新生成
- `标题不能为空` — 创建/更新时缺少标题
- `正文不能为空` — 创建时缺少正文
- `文章不存在` — get/update/delete 时 cid 不存在
- `内容疑似包含敏感信息` — 正文检测到手机号/身份证/银行卡

---

## 使用流程（AI 执行）

### 发布文章流程
1. **检测触发词** — 用户说"发文章到博客"等
2. **读取配置** — 从 `config.json` 读取 `domain` 和 `token`
3. **未配置时提示** — 给出配置命令，等待用户配置
4. **已配置时确认分类/标签** — 可选，使用默认值或用户自定义
5. **组织内容** — 整理标题、正文、标签、分类
6. **调用 API** — `action: submit`
7. **反馈结果** — 告知用户已保存，返回 cid 和状态

### 查询文章流程
1. **检测触发词** — 用户说"查看文章列表"等
2. **读取配置** — 确认已配置
3. **确认查询条件** — 页码、状态过滤等（可选）
4. **调用 API** — `action: list` 或 `action: get`
5. **展示结果** — 格式化输出文章信息

### 更新文章流程
1. **检测触发词** — 用户说"更新这篇文章"等
2. **确认目标** — 通过 cid 或标题定位文章
3. **确认修改内容** — 哪些字段需要更新
4. **调用 API** — `action: update`
5. **反馈结果** — 告知更新成功

### 删除文章流程
1. **检测触发词** — 用户说"删除这篇文章"等
2. **确认目标** — 通过 cid 定位文章
3. **二次确认** — 提醒用户删除不可逆
4. **调用 API** — `action: delete`
5. **反馈结果** — 告知删除成功

---

## 注意事项

- **Token 安全**：Token 是访问密钥，不要在公开对话中泄露。建议用户将 Token 保存在本地配置中。
- **正文长度**：不超过 50KB（约 5 万字），超长内容应分多篇发布。
- **敏感信息**：API 会自动拦截手机号、身份证号、银行卡号。如果内容被拦截，需用户人工确认后重试。
- **图片处理**：不支持直接上传图片。图片需使用外部图床 URL，在正文用 Markdown 图片语法引用。
- **删除不可逆**：删除操作会永久删除文章及关联关系，执行前务必二次确认。
- **升级风险**：Typecho 升级后部分核心文件可能被覆盖，可能导致 API 失效。需关注插件文档中的修改记录。

---

## 快速配置（供用户参考）

**首次使用需要配置的信息：**

| 信息 | 获取方式 |
|------|---------|
| 博客地址 | 你的 Typecho 博客 URL，如 `https://www.example.com` |
| API Token | 后台 → 插件 → OpenClawTypecho → 设置 → 点击"🔑 自动生成随机 Token" → 复制 |

**配置一次后，AI 即可直接管理您的博客文章，无需重复询问。**
