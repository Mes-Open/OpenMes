# COA PDF 字体

本目录的 `NotoSansSC-Regular.ttf` 与 `NotoSansSC-Bold.ttf` 用于 COA PDF 的中文渲染。

- 字体：Noto Sans SC（思源黑体简体中文字形）
- 来源：Google Fonts（`fonts.gstatic.com` 的 TrueType 版本）
- 许可：SIL Open Font License 1.1，可随软件分发
- 使用方式：`CoaController::registerCjkFont()` 在 dompdf 渲染前通过 `FontMetrics::registerFont()` 注册，模板中用 `font-family: "CoaNotoSans"` 引用

不要改回 CSS `@font-face` 指向本地文件：dompdf 的 `allowed_protocols` 默认不允许 `file://`，会直接报错并回退到无中文字形的 DejaVu。
