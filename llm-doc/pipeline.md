# handler — Layer 2: `AbstractPipelineHandler`, `Output`, `OutputType`

## `AbstractPipelineHandler` (extends `AbstractHandler`)

Constructor: the 7 services of `AbstractHandler` + `protected CurrentRoute $currentRoute` (route arguments: `$this->currentRoute->getArgument('id')`).

`handle()` assigns `$this->request`, then runs (`AbstractPipelineHandler.php:83-97`):

| Step | Default | Role |
|---|---|---|
| `setup(): ?Output` | `null` | loading, checks; an `Output` goes straight to `output()` |
| `setupMethod(): ?Output` | `null` | verb-dependent loading; called only if `setup()` returned `null`; an `Output` goes straight to `output()` |
| `process(): Output` | abstract | business logic |
| `prepareOutputData(Output $output): Output` | returns `$output` | completes the `Output` of `process()` only |
| `download(): ?string` | `null` | binary content for `OutputType::Download` |
| `output(Output $output): ResponseInterface` | see below | dispatch by type |

Traces: normal flow `setup,setupMethod,process,prepareOutputData`; `setup()` returning an `Output` → `setup` only.

## `output()`: type → response

| `OutputType` | Call | `params` read |
|---|---|---|
| `Render` | `render($view ?? '', $params)` | view parameters |
| `Partial` | `renderPartial($view ?? '', $params)` | view parameters |
| `Json` | `renderJson($params)` | the JSON payload |
| `Redirect` | `redirect($params['route'] ?? '', $params['params'] ?? [])`, always 302 | `route`, `params` |
| `Download` | `downloadContent(download() ?? '', $params['filename'] ?? 'download', ['mimeType' => $params['mimeType'] ?? 'application/octet-stream'])`, always `attachment` | `filename`, `mimeType` |

- `Redirect` to `articles.view` with `id` 7 → `302 | /articles/7`; without `route` → `RouteNotFoundException: Cannot generate URI for route ""`.
- `Download` without `params` → `application/octet-stream | attachment; filename="download"`; `download()` returning `null` → empty body.

## `Output`

```php
new Output(OutputType $type, array $params = [], ?string $view = null);
```

| Method | Returns |
|---|---|
| `getType()`, `getParams()`, `getView()` | the constructor values |
| `withType(OutputType)`, `withParams(array)`, `withView(?string)` | a new instance (`static`) |
| `withMergedParams(array $params)` | a new instance with `[...$old, ...$params]`: on a same string key, `$params` wins |

- Immutable (`private readonly` properties); a subclass keeps its type through the `with*()` methods.

## `OutputType` (string enum)

`Render = 'render'`, `Partial = 'partial'`, `Json = 'json'`, `Redirect = 'redirect'`, `Download = 'download'`.
