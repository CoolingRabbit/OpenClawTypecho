# OpenClawTypecho

> **OpenClaw Typecho Skill** — 让 OpenClaw 等 AI 自动向 Typecho 博客推送文章，构建 AI 知识库。

---

## 功能

- 为 OpenClaw 等 AI 服务提供 REST API 端点
- 自动创建文章到 Typecho 博客，支持 Markdown 格式
- 自动创建分类、标签
- Token 鉴权 + 敏感内容过滤
- 文章状态控制：待审核 / 草稿 / 私密 / 隐藏

---

## 安装

1. 将 `Plugin.php` 和 `Action.php` 上传到 Typecho 服务器 `usr/plugins/OpenClawTypecho/`
2. 后台 → 插件 → 启用 **OpenClawTypecho** → 进入设置

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
| `markdown` | boolean | 否 | `true` | 是否 Markdown 格式。不传时自动加 `<!--markdown-->` 标记 |
| `category` | string | 否 | `AI知识库`（插件设置） | 分类名称。不存在时自动创建 |
| `tags` | array | 否 | `[]` | 标签数组 |
| `slug` | string | 否 | 自动生成 | URL 缩略名（仅限字母、数字、`-`、`_`） |
| `status` | string | 否 | `waiting` | 文章状态 |

**状态选项：**

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

## 给 AI 的接入模板

参考 `OpenClaw Typecho Skill.md` — 让 AI 按流程引导用户填写博客地址和 Token 后即可自动发布。

---

## 环境要求

- Typecho ≥ 1.2.0
- PHP ≥ 7.4

---

## 许可证

GPL-3.0
