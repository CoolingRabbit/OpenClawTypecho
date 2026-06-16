# OpenClaw Typecho Skill

> **用途**：当 Kimi 需要将生成的内容保存到 Typecho 博客时，按此流程操作。  
> **版本**：1.0.0

---

## 触发条件

以下关键词触发发布操作：
- "发文章到博客" / "保存到博客"
- "AI 知识库归档" / "归档到博客"
- "Typecho 发布" / "自动发布"
- "保存这篇笔记"
- "生成博客文章"

---

## 接入流程（AI 引导用户）

### 第 1 步：确认博客地址

**AI 询问用户：**
> 您想将内容发布到哪个 Typecho 博客？请提供博客地址（如 `https://www.example.com`）。

**用户回复后，AI 记录：**
- `DOMAIN` = 用户提供的域名（如 `https://www.example.com`）

**如果没有提供，AI 使用默认值：**
- `DOMAIN` = `https://www.kjifds.top`（你自己的博客地址，可根据需要修改）

---

### 第 2 步：确认 API Token

**AI 询问用户：**
> 请提供 OpenClawTypecho 插件的 API Token。获取方式：登录博客后台 → 插件 → OpenClawTypecho → 设置 → 复制 Token 字段。

**用户回复后，AI 记录：**
- `TOKEN` = 用户提供的 Token

**如果用户没有 Token：**
> 您还没有配置 Token，请先去博客后台生成：插件 → OpenClawTypecho → 点击"🔑 自动生成随机 Token" → 复制保存。

---

### 第 3 步：确认作者和分类（可选）

**AI 询问用户：**
> 文章归属作者和分类使用插件默认值吗？（默认作者：插件设置中选择的用户；默认分类：插件设置中的默认分类）

**用户可自定义：**
- `CATEGORY` = 自定义分类名称（如"技术笔记"）
- `TAGS` = 标签数组（如 `["Python", "教程"]`）

---

### 第 4 步：调用 API 发布

**确认信息完整后，AI 发送请求：**

```
POST {DOMAIN}/index.php/action/openclaw-submit
Authorization: Bearer {TOKEN}
Content-Type: application/json
```

---

## 请求参数

| 字段 | 类型 | 必须 | 说明 | 默认值 |
|------|------|------|------|--------|
| `title` | string | ✅ | 文章标题 | — |
| `text` | string | ✅ | 文章正文（Markdown） | — |
| `markdown` | boolean | ❌ | 是否 Markdown | `true` |
| `category` | string | ❌ | 分类名称 | 插件默认分类 |
| `tags` | array | ❌ | 标签数组 | `[]` |
| `slug` | string | ❌ | URL 缩略名 | 自动生成 |
| `status` | string | ❌ | 文章状态 | `waiting` |

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
- AI：`LLM`, `Kimi`, `Prompt`, `RAG`
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
2. **询问博客地址** — 如果未配置，请用户提供 `DOMAIN`
3. **询问 Token** — 如果未配置，请用户提供 `TOKEN`
4. **询问分类/标签** — 可选，使用默认值或用户自定义
5. **组织内容** — 整理标题、正文、标签、分类
6. **调用 API** — 发送 POST 请求
7. **反馈结果** — 告知用户已保存，状态是待审核/草稿等

---

## 注意事项

- **Token 安全**：Token 是访问密钥，不要在公开对话中泄露。建议用户将 Token 保存在环境变量或安全配置中。
- **正文长度**：不超过 50KB（约 5 万字），超长内容应分多篇发布。
- **敏感信息**：API 会自动拦截手机号、身份证号、银行卡号。如果内容被拦截，需用户人工确认后重试。
- **图片处理**：不支持直接上传图片。图片需使用外部图床 URL，在正文用 Markdown 图片语法引用。
- **升级风险**：Typecho 升级后部分核心文件可能被覆盖，可能导致 API 失效。需关注插件文档中的修改记录。

---

## 快速配置（供用户参考）

**用户需要准备的信息：**

| 信息 | 获取方式 |
|------|---------|
| 博客地址 | 你的 Typecho 博客 URL，如 `https://www.example.com` |
| API Token | 后台 → 插件 → OpenClawTypecho → 设置 → 点击"🔑 自动生成随机 Token" → 复制 |
| 默认作者 | 后台 → 插件 → OpenClawTypecho → 设置 → 选择 AI 文章归属作者 |
| 默认分类 | 后台 → 插件 → OpenClawTypecho → 设置 → 默认文章分类 |

**将这些信息提供给 AI 后，AI 即可自动发布文章到您的博客。**
