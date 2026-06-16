# OpenClawTypecho

> **OpenClaw Typecho Skill** — 让 OpenClaw（如 Kimi）自动向 Typecho 博客推送文章，构建 AI 知识库。

---

## 功能

- 为 OpenClaw / Kimi 等 AI 服务提供 REST API 端点
- 自动创建文章到 Typecho 博客，支持 Markdown 格式
- 自动创建分类、标签
- Token 鉴权 + 敏感内容过滤
- 文章状态控制：待审核 / 草稿 / 私密 / 隐藏

---

## 安装

1. 下载本插件
2. 将 `Plugin.php` 和 `Action.php` 上传到 Typecho 服务器 `usr/plugins/OpenClawTypecho/`
3. 后台 → 插件 → 启用 **OpenClawTypecho** → 进入设置

### 配置项

| 配置项 | 说明 |
|--------|------|
| **API 访问密钥 (Token)** | 点击"🔑 自动生成"按钮生成，AI 调用时携带 `Authorization: Bearer <token>` |
| **AI 文章归属作者** | 下拉框选择用户。建议创建专用用户 `ai` |
| **默认文章分类** | 如 `AI知识库`。不存在时自动创建 |

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

请求体：

| 字段 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| `title` | string | ✅ | — | 文章标题 |
| `text` | string | ✅ | — | 文章正文 |
| `markdown` | boolean | ❌ | `true` | 是否 Markdown 格式 |
| `category` | string | ❌ | 默认分类 | 分类名称 |
| `tags` | array | ❌ | `[]` | 标签数组 |
| `slug` | string | ❌ | 自动生成 | URL 缩略名 |
| `status` | string | ❌ | `waiting` | 状态选项见下 |

**状态选项：** `waiting`（待审核） / `draft`（草稿） / `private`（私密） / `hidden`（隐藏）

---

## 安全机制

- Bearer Token 鉴权
- 只接受 POST + JSON
- 敏感内容过滤（手机号、身份证、银行卡号）
- XSS 过滤
- 标题 ≤ 200 字符，正文 ≤ 50KB

---

## 给 AI 的接入模板

参考 `AI-SKILL.md` — 让 AI 按流程引导用户填写博客地址和 Token 后即可自动发布。

---

## 环境要求

- Typecho ≥ 1.2.0（推荐 1.3.0）
- PHP ≥ 7.4（推荐 8.x）
- 开启伪静态（推荐 WordPress 规则）

---

## 许可证

MIT
