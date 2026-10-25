# blackcube/handler — LLM index

**Role:** base PSR-15 handlers for Yii3 applications: response helpers (view, JSON, redirect, download), view resolution by namespace, a pipeline with an `Output` DTO, a three-exchange AJAX dialog flow backed by the session.

## Package card

| | |
|---|---|
| Namespace | `Blackcube\Handler` |
| Layer 1 | `AbstractHandler` (implements `RequestHandlerInterface`, no `handle()`), `HandlerConfig` |
| Layer 2 | `AbstractPipelineHandler`, `Output`, `OutputType` (enum `Render`, `Partial`, `Json`, `Redirect`, `Download`) |
| Layer 3 | `Dialog\AbstractDialogInit`, `Dialog\AbstractDialogConfirm` |
| Requires | PHP ^8.2, `psr/http-factory` ^1.1, `psr/http-message` ^2.0, `psr/http-server-handler` ^1.0, `psr/log` ^3.0, `yiisoft/aliases` ^3.1, `yiisoft/data-response` ^2.2, `yiisoft/http` ^1.3, `yiisoft/router` ^4.0, `yiisoft/yii-view-renderer` ^7.4. Layer 3 also needs `blackcube/bleet`, `blackcube/bridge-form-model`, `yiisoft/session` (declared as `suggest`). |
| Yii config | No config-plugin: the application declares `HandlerConfig`. |
| License | BSD-3-Clause |

## Rules

- A direct `AbstractHandler` subclass writes `handle()` and assigns `$this->request = $request` before any helper reading the request (`getBodyParams()`, `isAjax()`, `isAjaxify()`); otherwise `Error: Typed property …::$request must not be accessed before initialization`.
- Handler views: `render()` / `renderPartial()` resolve under `HandlerConfig::viewsAlias` through `extractViewPath()`: simple name → `<namespace folder>/<name>`, `//x` → `x`, `/x` = absolute file path.
- Inside a view, `$this->render('_x')` looks for `<viewsAlias>/_x.php` (not the folder of the calling view); `$this->render('//x')` goes through yiisoft/view, under `yiisoft/view.basePath`.
- Pipeline: `setup()` → `setupMethod()` → `process()` → `prepareOutputData()` → `output()`. An `Output` returned by `setup()` or `setupMethod()` goes straight to `output()`; `prepareOutputData()` applies only to the `Output` of `process()`.
- `Output` is immutable; its withers return `static`: a subclass keeps its type.
- Dialog: the form implements `ExtractDataInterface`; the session holds only `extractData()`; init and confirm reload it with `load($data, '')` then revalidate; a field without a validation rule is not reloaded; context comes from the route.

## Parts

| File | Content | Read when |
|---|---|---|
| `handler.md` | `AbstractHandler`: constructor (7 services), helpers and their responses (`render` 200, `redirect` 302 by default, download headers), `downloadFile()` exceptions, AJAX detection, view resolution (`simple` / `//` / `/` table, edge cases). `HandlerConfig`: 4 properties, defaults. | before writing a layer 1 handler |
| `pipeline.md` | `AbstractPipelineHandler`: steps, short-circuit, `CurrentRoute`; `OutputType` → method mapping, `params` shape per type, defaults (`Download`: `filename` `download`, `application/octet-stream`, content from `download()`); `Redirect` without `route` → `RouteNotFoundException`. | before writing a layer 2 handler |
| `dialog.md` | The 3 exchanges and routes, abstract methods and signatures, session content, `storeInSession()`, built-in errors (exact messages), entry removal on confirm, form requirements (`ExtractDataInterface`, rules). | before writing a dialog |
| `integration.md` | Yii: `HandlerConfig` DI definition, services expected from the container, aliases, dialog routes. | before plugging the package in |
| `extension.md` | `protected` points (`getLayout()`, `getViewPath()`, `extractViewPath()`, pipeline steps, `output()`, `download()`), `Output` subclass; what does not extend (dialog messages hardcoded in `setup()`, `readonly` `HandlerConfig` constructor). | before extending |
