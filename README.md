# OpenClawTypecho

> 一台廉价的 PHP 虚拟主机 + 一个 Typecho 博客 + 这个插件 = **AI 直接管理的在线知识库**。

装完插件，把 SKILL.md 丢给 AI，即可开始归档、检索、更新文章。无需维护索引文件，无需配置目录规则，无需担心 Token 暴雷。

**在线演示** → [kjifds.top](https://www.kjifds.top/)

---

## 为什么用这个方案

| | OpenClawTypecho | Obsidian + AI |
|---|---|---|
| **成本** | 虚拟主机 ¥35/年 | 本地免费，但 AI 插件/API 额外计费 |
| **配置** | 装插件 → 配置 Token → 完事 | 需维护 CLAUDE.md、index.md、目录结构 |
| **访问** | 天生在线，URL 即可分享 | 本地优先，分享需配同步 |
| **AI 操作** | API 直写数据库，无 Token 压力 | AI 需读取大量 Markdown，上下文易爆炸 |
| **适用** | 快速归档、博客发布、轻量知识库 | 深度研究、复杂图谱、本地编译 |

如果你需要的是**随手丢给 AI 就能自动归档、随时在线查看**的知识库——这个方案就是为你准备的。

---

## 功能

- 为 OpenClaw 等 AI 服务提供 REST API 端点
- **完整的文章 CRUD**：创建、查询列表、查询详情、更新、删除
- 自动创建分类、标签
- Bearer Token 鉴权 + 敏感内容过滤
- 文章状态控制：publish / waiting / draft / private / hidden
- 配套 SKILL.md 写作规范（分类标签体系、脱敏规则、发布检查清单）

---

## 安装

### 环境要求

- Typecho ≥ 1.2.0
- PHP ≥ 7.4（推荐 PHP 8.0+）
- MySQL 5.7+ / MariaDB 10.3+

### 步骤

1. 下载 [最新版本](https://github.com/CoolingRabbit/OpenClawTypecho/releases)
2. 解压到 `usr/plugins/OpenClawTypecho/`（确保目录名正确）
3. 后台 → 插件 → 启用 **OpenClawTypecho**
4. 点击 **🔑 自动生成随机 Token**，复制保存
5. 选择 AI 发布文章使用的作者账户（建议创建独立用户，用户组设为「贡献者」）

### 配置完成后

将以下信息提供给 AI 助手：

| 信息 | 说明 |
|------|------|
| **博客地址** | 如 `https://www.example.com` |
| **API Token** | 上一步生成的 Token |
| **SKILL.md** | 本仓库的 [SKILL.md](https://github.com/CoolingRabbit/OpenClawTypecho/blob/main/SKILL.md) 文件 |

AI 阅读 SKILL.md 后即可按规范发布文章。

---

## API 参考

### 通用说明

- **端点**：`https://<你的博客地址>/action/openclaw-submit`
- **方法**：POST
- **Content-Type**：`application/json`
- **鉴权**：请求头 `Authorization: Bearer <token>`

### 1. 创建文章 — `submit`

```json
{
  "action": "submit",
  "title": "文章标题",
  "text": "## Markdown 正文",
  "category": "技术笔记",
  "tags": ["Typecho", "PHP", "插件开发"],
  "status": "publish"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | ✅ | 固定值 `submit` |
| `title` | string | ✅ | 标题，≤ 200 字符 |
| `text` | string | ✅ | 正文，Markdown 格式，≤ 50KB |
| `markdown` | boolean | 否 | 是否 Markdown，默认 `true` |
| `category` | string | 否 | 分类名，不存在时自动创建 |
| `tags` | array | 否 | 标签数组 |
| `slug` | string | 否 | 缩略名，仅允许 `[a-zA-Z0-9-_]` |
| `status` | string | 否 | 状态，默认 `waiting` |

### 2. 查询列表 — `list`

```json
{
  "action": "list",
  "page": 1,
  "pageSize": 10,
  "status": "publish",
  "category": "技术笔记"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | ✅ | 固定值 `list` |
| `page` | int | 否 | 页码，默认 `1` |
| `pageSize` | int | 否 | 每页数量，默认 `10`，最大 `50` |
| `status` | string | 否 | 按状态过滤 |
| `category` | string | 否 | 按分类过滤 |

### 3. 查询单篇 — `get`

```json
{
  "action": "get",
  "cid": 42
}
```

### 4. 更新文章 — `update`

```json
{
  "action": "update",
  "cid": 42,
  "title": "更新后的标题",
  "text": "更新后的完整正文",
  "tags": ["新标签"]
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `action` | string | ✅ | 固定值 `update` |
| `cid` | int | ✅ | 文章 ID |
| `title` | string | 否 | 新标题 |
| `text` | string | 否 | 新正文（**传入则整体替换原文，不是增量追加**） |
| `category` | string | 否 | 新分类 |
| `tags` | array | 否 | 新标签 |
| `slug` | string | 否 | 新缩略名 |
| `status` | string | 否 | 新状态 |

> **注意**：未传的字段保持原值不变。更新正文时需先 `get` 获取原文，修改后传入完整正文。

### 5. 删除文章 — `delete`

```json
{
  "action": "delete",
  "cid": 42
}
```

> 删除不可逆，执行前务必确认。

---

## 文章状态

| 状态 | 数据库存储 | 说明 |
|------|-----------|------|
| `publish` | `type=post, status=publish` | 已发布，公开可见 |
| `waiting` | `type=post, status=waiting` | 待审核，后台手动发布 |
| `draft` | `type=post_draft, status=publish` | 草稿，不公开 |
| `private` | `type=post, status=private` | 私密，仅作者可见 |
| `hidden` | `type=post, status=hidden` | 隐藏，可通过 URL 访问但不在列表中 |

---

## 安全机制

| 机制 | 说明 |
|------|------|
| Bearer Token 鉴权 | 时序安全比较（`hash_equals`），防止旁路攻击 |
| POST + JSON 限制 | 拒绝 GET 请求和表单提交，减少 CSRF 风险 |
| 敏感信息拦截 | 自动检测手机号、身份证号、银行卡号 |
| XSS 过滤 | 标签字段经 `Validate::xssCheck()` 处理 |
| 长度限制 | 标题 ≤ 200 字符，正文 ≤ 50KB |
| 独立作者账户 | 建议为 AI 创建独立贡献者账户，区分人工与 AI 文章 |

---

## SKILL.md — AI 写作规范

本仓库附带 [SKILL.md](https://github.com/CoolingRabbit/OpenClawTypecho/blob/main/SKILL.md)，是给 AI 助手的完整操作规范，包含：

- **分类白名单**：技术笔记 / 部署教程 / 运维记录
- **标签规则**：3-5 个，中英文判定标准，禁用词清单
- **写作视角**：第三人称技术视角，附正反例
- **脱敏规则**：8 类敏感信息处理标准
- **结构模板**：每个分类各一套写作模板
- **发布检查清单**：10 项发布前检查
- **更新流程**：list → get → 完整正文 update，禁止同主题新建

将 SKILL.md 提供给 AI 助手后，AI 即可按规范操作博客。

---

## 错误响应

所有错误统一返回：

```json
{
  "success": false,
  "message": "错误描述"
}
```

| HTTP 状态码 | 含义 | 常见原因 |
|-------------|------|---------|
| 400 | 请求参数错误 | 缺少必填字段、长度超限、敏感信息 |
| 401 | 鉴权失败 | Token 错误或过期 |

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

[GPL-3.0](LICENSE)

Copyright (c) 2026 CoolingRabbit
