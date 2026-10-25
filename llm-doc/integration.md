# handler — Yii integration

## Split

- Package: no config-plugin (no DI definition, no params).
- Application: `HandlerConfig` when its values differ from the defaults, routes, views and layout.

## `HandlerConfig`

Without a definition, the container builds `HandlerConfig` with its defaults (`App\Handlers\`, `@src/Views`, `@src/Views/Layouts/main.php`, `debug` `false`). Other values need a definition, e.g. `config/common/di/handler.php`:

```php
use Blackcube\Handler\HandlerConfig;

return [
    HandlerConfig::class => [
        'class' => HandlerConfig::class,
        '__construct()' => [
            'handlerNamespacePrefix' => 'App\\Handlers\\',
            'viewsAlias' => '@src/Views',
            'layoutAlias' => '@src/Views/Layouts/main.php',
            'debug' => $debug,
        ],
    ],
];
```

`$debug` comes from the application environment.

## Services expected from the container

| Layer | Services |
|---|---|
| 1 | `LoggerInterface`, `HandlerConfig`, `WebViewRenderer`, `ResponseFactoryInterface`, `JsonResponseFactory`, `UrlGeneratorInterface`, `Aliases` |
| 2 | + `CurrentRoute` |
| 3 | + `SessionInterface` |

A Yii3 web application provides them: in a standard web container, pipeline and dialog handlers are built by autowiring.

## Aliases and views

- `viewsAlias` and `layoutAlias` must resolve through `Aliases` (defaults: `@src` required).
- Views rendered by a handler live under `viewsAlias`; `yiisoft/view.basePath` only matters for `$this->render('//…')` written inside views.

## Routes

As in dboard (`config/routes.php`): `Route::methods([...], '<pattern>')->action(<Handler>::class)->name('<name>')`.

Dialog: the argument is named exactly `confirmationId` (read with `getArgument('confirmationId')`):

```php
Route::post('/articles/{id}/delete')
    ->action(DeleteSubmit::class)
    ->name('articles.delete'),
Route::get('/articles/{id}/delete/{confirmationId}')
    ->action(DeleteSubmit::class)
    ->name('articles.delete.modal'),
Route::post('/articles/{id}/delete/{confirmationId}/confirm')
    ->action(DeleteConfirm::class)
    ->name('articles.delete.confirm'),
```
