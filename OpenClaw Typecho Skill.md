# OpenClaw Typecho Skill

> **用途**：当 AI 需要将生成的内容保存到 Typecho 博客时，按此流程操作。  
> **版本**：1.0.0

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

```
kimi skill config typecho-publisher domain=<博客地址> token=<Token> [default_category=<分类>] [default_tags=<标签1,标签2>]
```

**配置示例：**
```
kimi skill config typecho-publisher domain=https://www.example.com token=AbCdEfGh123456 default_category=技术笔记 default_tags=笔记,Typecho,教程
```

### 配置获取方式

- **博客地址**：你的 Typecho 博客 URL，如 `https://www.example.com`
- **API Token**：登录博客后台 → 插件 → OpenClawTypecho → 设置 → 点击"🔑 自动生成随机 Token" → 复制

---

## 触发条件

以下关键词触发发布操作：
- "发文章到博客" / "保存到博客"
- "AI 知识库归档" / "归档到博客"
- "Typecho 发布" / "自动发布"
- "保存这篇笔记"
- "生成博客文章"

---

## 接入流程

### 第 1 步：读取配置

**检测触发词后，首先读取本地配置：**

- 若 `domain` 和 `token` 均已配置 → 进入第 2 步
- 若任一未配置 → **提示用户配置，不继续执行**

> 您还没有配置 Typecho 博客信息，请先配置：
> ```
> kimi skill config typecho-publisher domain=<博客地址> token=<Token>
> ```
> 配置完成后再次触发即可自动发布。

---

### 第 2 步：确认分类和标签（可选）

**AI 询问用户：**
> 文章使用默认分类和标签吗？（默认分类：`{config.default_category}`；默认标签：`{config.default_tags}`）

**用户可自定义：**
- `CATEGORY` = 自定义分类名称（如"技术笔记"）
- `TAGS` = 标签数组（如 `["Python", "教程"]`）

**用户可直接回答"默认"或直接跳过 → 使用配置中的默认值。**

---

### 第 3 步：调用 API 发布

**确认信息完整后，AI 发送请求：**

```
POST {config.domain}/index.php/action/openclaw-submit
Authorization: Bearer {config.token}
Content-Type: application/json
```

---

## 请求参数

| 字段 | 类型 | 必填 | 说明 | 默认值 |
|------|------|------|------|--------|
| `title` | string | ✅ | 文章标题 | — |
| `text` | string | ✅ | 文章正文（Markdown） | — |
| `markdown` | boolean | 否 | 是否 Markdown | `true` |
| `category` | string | 否 | 分类名称 | `config.default_category` 或 `AI知识库` |
| `tags` | array | 否 | 标签数组 | `config.default_tags` 或 `[]` |
| `slug` | string | 否 | URL 缩略名 | 自动生成 |
| `status` | string | 否 | 文章状态 | `waiting` |

**状态选项：**
| 状态 | 使用场景 |
|------|---------|
| `waiting` | **默认推荐**。待审核，需后台手动发布。 |
| `draft` | 草稿 |
| `private` | 私密 |
| `hidden` | 隐藏 |

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

## 请求示例

```json
POST /index.php/action/openclaw-submit
Authorization: Bearer sk-live-xxx
Content-Type: application/json

{
  "title": "Typecho 博客 HTTPS 配置踩坑记录",
  "text": "## 问题描述\n\n配置 SSL 证书后，网站间歇性无法访问...\n\n## 排查过程\n\n1. 检查证书有效性\n2. 检查 DNS 解析\n3. 联系 CDN 服务商\n\n## 解决方案\n\n...",
  "markdown": true,
  "category": "技术笔记",
  "tags": ["Typecho", "SSL", "CDN", "踩坑"],
  "status": "waiting"
}
```

---

## 响应处理

**成功（HTTP 200）：**
```json
{
  "success": true,
  "message": "文章已保存",
  "cid": 42,
  "status": "waiting"
}
```

**失败（HTTP 400 / 401）：**
```json
{
  "success": false,
  "message": "标题不能为空"
}
```

**常见错误：**
- `401 鉴权失败` — Token 错误或过期，告知用户重新生成
- `标题不能为空` — 检查参数
- `正文不能为空` — 检查参数
- `内容疑似包含敏感信息` — 正文检测到手机号/身份证/银行卡，需用户确认后重试

---

## 使用流程（AI 执行）

1. **检测触发词** — 用户说"发文章到博客"等
2. **读取配置** — 从 `config.json` 读取 `domain` 和 `token`
3. **未配置时提示** — 给出配置命令，等待用户配置
4. **已配置时确认分类/标签** — 可选，使用默认值或用户自定义
5. **组织内容** — 整理标题、正文、标签、分类
6. **调用 API** — 发送 POST 请求
7. **反馈结果** — 告知用户已保存，状态是待审核/草稿等

---

## 注意事项

- **Token 安全**：Token 是访问密钥，不要在公开对话中泄露。建议用户将 Token 保存在本地配置中。
- **正文长度**：不超过 50KB（约 5 万字），超长内容应分多篇发布。
- **敏感信息**：API 会自动拦截手机号、身份证号、银行卡号。如果内容被拦截，需用户人工确认后重试。
- **图片处理**：不支持直接上传图片。图片需使用外部图床 URL，在正文用 Markdown 图片语法引用。
- **升级风险**：Typecho 升级后部分核心文件可能被覆盖，可能导致 API 失效。需关注插件文档中的修改记录。

---

## 快速配置（供用户参考）

**首次使用需要配置的信息：**

| 信息 | 获取方式 |
|------|---------|
| 博客地址 | 你的 Typecho 博客 URL，如 `https://www.example.com` |
| API Token | 后台 → 插件 → OpenClawTypecho → 设置 → 点击"🔑 自动生成随机 Token" → 复制 |

**配置一次后，AI 即可直接发布文章到您的博客，无需重复询问。**
