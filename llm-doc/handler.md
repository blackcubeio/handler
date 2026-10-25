# handler — Layer 1: `AbstractHandler`, `HandlerConfig`

## `HandlerConfig`

```php
new HandlerConfig(
    string $handlerNamespacePrefix = 'App\\Handlers\\',
    string $viewsAlias = '@src/Views',
    string $layoutAlias = '@src/Views/Layouts/main.php',
    bool $debug = false,
);
```

The 4 properties are `public readonly`.

| Property | Used by |
|---|---|
| `handlerNamespacePrefix` | `extractViewPath()`: prefix stripped from the class name |
| `viewsAlias` | `getViewPath()` = views directory of `render()` / `renderPartial()` |
| `layoutAlias` | `getLayout()` = layout of `render()` |
| `debug` | copied to `$this->debug`; not read elsewhere by the package |

## `AbstractHandler` (implements `RequestHandlerInterface`)

```php
public function __construct(
    protected LoggerInterface $logger,
    protected HandlerConfig $config,
    protected WebViewRenderer $viewRenderer,
    protected ResponseFactoryInterface $responseFactory,
    protected JsonResponseFactory $jsonResponseFactory,
    protected UrlGeneratorInterface $urlGenerator,
    protected Aliases $aliases,
);
```

- `handle()` is not implemented: the subclass writes it.
- `protected ServerRequestInterface $request` is not initialized: `handle()` assigns it before any helper reading the request, otherwise `Error: Typed property …::$request must not be accessed before initialization`.

| Helper (`protected`) | Response |
|---|---|
| `render(string $view, array $parameters = [])` | 200; view resolved under `getViewPath()`, layout `getLayout()` |
| `renderPartial(string $view, array $parameters = [])` | 200, no layout |
| `renderJson(array $data)` | `JsonResponseFactory`, `Content-Type: application/json; charset=UTF-8` |
| `redirect(string $routeName, array $parameters = [], int $status = 302)` | `Location` = `UrlGenerator::generate()`; unknown route → `RouteNotFoundException` |
| `downloadContent(string $content, string $filename, array $options = [])` | `Content-Type` = `options['mimeType']` (default `application/octet-stream`), `Content-Disposition: <options['attachment'] ?? 'attachment'>; filename="<filename>"` (`$filename` inserted as is) |
| `downloadFile(string $filepath, ?string $filename = null, array $options = [])` | reads the file then `downloadContent()`; `filename` default `basename()`; MIME = `options['mimeType']`, else `mime_content_type()`, else `application/octet-stream`; missing file → `RuntimeException` `File not found: <path>`; unreadable → `RuntimeException` `Failed to read file: <path>` |
| `getBodyParams(): ?array` | `null` on `GET`, otherwise `getParsedBody()` |
| `isAjax(): bool` | `X-Requested-With: XMLHttpRequest` |
| `isAjaxify(): bool` | `X-Requested-For: Ajaxify` |

## View resolution

`render()` / `renderPartial()` call `withViewPath(getViewPath())` then render `extractViewPath($view)`.

| Name passed | `extractViewPath()` returns | File |
|---|---|---|
| `detail` in `App\Handlers\Admin\Articles\Detail` | `Admin/Articles/detail` | `<viewsAlias>/Admin/Articles/detail.php` |
| `not-found` in `App\Handlers\NotFound` | `not-found` | `<viewsAlias>/not-found.php` |
| `x` in `Other\Place\Thing` (outside the prefix) | `Other/Place/x` | `<viewsAlias>/Other/Place/x.php` |
| `//Commons/_c` | `Commons/_c` | `<viewsAlias>/Commons/_c.php` |
| `/abs/x` | `/abs/x` | `/abs/x.php` |

- `yiisoft/view.basePath` plays no part in these renders: a `WebView` without `basePath` renders the view.
- Inside a view: `$this->render('_x')` looks for `<viewsAlias>/_x.php`; `$this->render('//x')` looks under `yiisoft/view.basePath`.
