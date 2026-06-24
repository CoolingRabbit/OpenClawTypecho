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

## API 速查

### 通用说明

- **端点**：`{domain}/index.php/action/openclaw-submit`
- **方法**：POST
- **Content-Type**：`application/json`
- **鉴权**：请求头 `Authorization: Bearer <token>`

### 五个操作

| action | 用途 | 必填字段 |
|--------|------|---------|
| `submit` | 创建文章 | `title`, `text` |
| `list` | 查询列表 | — |
| `get` | 查询单篇 | `cid` |
| `update` | 更新文章 | `cid` + 至少一个修改字段 |
| `delete` | 删除文章 | `cid` |

完整参数和写作规范见 [SKILL.md](https://github.com/CoolingRabbit/OpenClawTypecho/blob/main/SKILL.md)。

---

## 限制与注意事项

### 功能限制

| 限制 | 说明 |
|------|------|
| **图片上传** | ❌ 不支持。图片需使用外部图床 URL，在正文用 Markdown 图片语法 `![alt](url)` 引用 |
| **正文长度** | 不超过 50KB（**字节长度**，中文约 1.6 万字），超长应分多篇 |
| **增量更新** | ❌ `update` 的 `text` 字段是**整体替换**，不是增量追加。更新前必须先 `get` 获取完整原文 |
| **敏感信息拦截** | 插件自动拦截手机号、身份证号、银行卡号。其他敏感信息（域名、IP、密码等）需 AI 在写作时主动处理 |
| **分类** | 不强制要求。传入空分类时文章无分类，传入新分类名时自动创建 |

### 状态默认值

- API 请求中不传 `status` 时，默认值为 `waiting`
- SKILL.md 建议 AI 显式传 `status: "publish"` 直接发布

---

## 文章状态

| 状态 | 数据库存储 | 说明 |
|------|-----------|------|
| `publish` | `type=post, status=publish` | 已发布，公开可见 |
| `waiting` | `type=post, status=waiting` | 待审核，后台手动发布 |
| `draft` | `type=post_draft, status=publish` | 草稿，不公开 |
| `private` | `type=post, status=private` | 私密，仅作者可见 |
| `hidden` | `type=post, status=hidden` | 隐藏，可通过 URL 访问但不在列表中 |

> `draft` 状态在数据库中存储为 `type=post_draft, status=publish`，这是 Typecho 的 draft 实现机制，调用方无需关心。

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
| 400 | 请求参数错误 | 缺少必填字段、长度超限、敏感信息、文章不存在 |
| 401 | 鉴权失败 | Token 未配置、格式错误、Token 无效 |

---

## 测试 API

使用 curl 快速测试：

```bash
curl -X POST https://your-blog.com/index.php/action/openclaw-submit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token-here" \
  -d '{"action":"list","page":1,"pageSize":5}'
```

---

## FAQ

### Q: API 返回 404 Not Found？
**A:** 检查以下三点：
1. 插件是否已在后台启用
2. 目录名是否为 `OpenClawTypecho`（大小写敏感）
3. 如果使用伪静态，确保 `index.php` 在路由中

### Q: 文章发布成功但前台看不到？
**A:** 检查 `status` 字段：
- `publish` → 前台可见
- `waiting` → 仅后台可见，需手动审核
- `draft` → 仅后台草稿箱可见

### Q: 更新文章后内容变少了？
**A:** `update` 的 `text` 字段是**整体替换**，不是增量追加。正确流程：
1. 先 `get` 获取完整原文
2. 在完整原文基础上修改
3. 将修改后的**完整正文**传入 `update`

### Q: 敏感信息被拦截，怎么排查？
**A:** 插件自动检测以下 3 类信息：
- 手机号（1 开头 11 位数字）
- 身份证号（15 或 18 位）
- 银行卡号（16-19 位数字）

如被拦截，请检查正文并将这些信息替换为占位符（如 `<phone>`）。

### Q: 分类/标签没有生效？
**A:** 分类/标签在传入非空值时才会创建和关联。如果传入空字符串或空数组，则不会设置分类/标签。

### Q: 如何修改文章状态？
**A:** 调用 `update` 并传入 `status` 字段即可，例如：
```json
{
  "action": "update",
  "cid": 42,
  "status": "publish"
}
```

---

## 更新日志

### v2.0.0
- 统一版本号，精简文档结构
- 增强错误提示信息
- 新增限制说明与 FAQ

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
