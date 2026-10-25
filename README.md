# Blackcube Handler

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/handler.svg)](https://packagist.org/packages/blackcube/handler)

PSR-15 handler abstractions and pipeline for Yii3 applications.

Three layers of abstract handlers, each optional:

- **`AbstractHandler`** — base PSR-15 handler with view rendering, JSON, redirect, download, AJAX helpers and namespace-based view resolution.
- **`AbstractPipelineHandler`** — four-step pipeline (`setup → setupMethod → process → prepareOutputData → output`) with an immutable `Output` DTO.
- **`Dialog\AbstractDialogInit`** / **`AbstractDialogConfirm`** — AJAX dialog flow (submit → modal → confirm) backed by session.

## Where it sits

```
┌────────────────────────────────────────────┐
│         Your Yii3 application              │
│   (handlers extend the abstract bases)     │
└─────────────────────┬──────────────────────┘
                      ↓
              ┌───────────────┐
              │ handler       │  ← this package
              │  (3 layers)   │
              └───────┬───────┘
                      ↓
        Yii3 / PSR-15 / PSR-7 services
        (WebViewRenderer, Aliases, Router…)
```

The Dialog layer additionally consumes `blackcube/bleet`, `blackcube/bridge-form-model` and `yiisoft/session` (declared as `suggest` — install only if you use it).

## Requirements

- PHP 8.2+
- Yii3 (`yiisoft/aliases`, `yiisoft/router`, `yiisoft/yii-view-renderer`, `yiisoft/data-response`, `yiisoft/http`)
- PSR-7, PSR-15, PSR-3
- For the Dialog layer: `blackcube/bleet`, `blackcube/bridge-form-model`, `yiisoft/session`

## Installation

```bash
composer require blackcube/handler
```

If you need the Dialog layer:

```bash
composer require blackcube/bleet blackcube/bridge-form-model yiisoft/session
```

## What it is

A small set of base classes that capture the patterns we keep reusing across every Blackcube Yii3 application:

- **Render a view** with a layout and a configurable view path.
- **Resolve view names** from the handler namespace — `Detail` in `App\Handlers\Admin\Articles\Detail` looks up `Admin/Articles/detail.php` automatically.
- **Run a structured pipeline** when a handler needs to load data, react to the HTTP verb, run business logic and produce a response.
- **Build AJAX dialogs** that submit a form, open a confirmation modal and confirm — without rewriting the session/validation dance every time.

The package is opinionated about *how* a handler is built, not about *what* it does.

## Quick Start

### A simple handler — extend `AbstractHandler`

```php
namespace App\Handlers;

use Blackcube\Handler\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Home extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        return $this->render('home', ['title' => 'Welcome']);
    }
}
```

### A pipeline handler — extend `AbstractPipelineHandler`

```php
namespace App\Handlers\Admin\Articles;

use App\Models\Article;
use Blackcube\Handler\AbstractPipelineHandler;
use Blackcube\Handler\Output;
use Blackcube\Handler\OutputType;
use Yiisoft\Db\Query\DataReaderInterface;

class Index extends AbstractPipelineHandler
{
    private ?DataReaderInterface $articles = null;

    protected function setup(): ?Output
    {
        $this->articles = Article::query()
            ->andWhere(['deletedAt' => null])
            ->orderBy(['createdAt' => SORT_DESC])
            ->each();

        return null;
    }

    protected function process(): Output
    {
        return new Output(OutputType::Render, [
            'articles' => $this->articles,
        ], 'index');
    }
}
```

`process()` returns an `Output` describing **what** to render. The pipeline dispatches it to the right method (`render`, `renderPartial`, `renderJson`, `redirect`, download).

### A dialog handler — extend `Dialog\AbstractDialogInit`

```php
namespace App\Handlers\Admin\Articles;

use App\Forms\ArticleDeleteForm;
use Blackcube\Bleet\Enums\DialogAction;
use Blackcube\Bleet\Helper\AureliaCommunication;
use Blackcube\BridgeFormModel\BridgeFormModel;
use Blackcube\BridgeFormModel\Interfaces\ExtractDataInterface;
use Blackcube\Handler\Dialog\AbstractDialogInit;
use Blackcube\Handler\Output;
use Blackcube\Handler\OutputType;

class DeleteDialog extends AbstractDialogInit
{
    protected function getSessionPrefix(): string
    {
        return 'article_delete_';
    }

    // ArticleDeleteForm implements ExtractDataInterface with ExtractDataTrait
    protected function createForm(): BridgeFormModel&ExtractDataInterface
    {
        return new ArticleDeleteForm();
    }

    // The validated form data is already in session under $confirmationId
    protected function handleSubmit(BridgeFormModel&ExtractDataInterface $form, string $confirmationId): Output
    {
        return new Output(OutputType::Json, [
            'modalUrl' => $this->urlGenerator->generate('articles.delete.modal', [
                'id' => $this->currentRoute->getArgument('id'),
                'confirmationId' => $confirmationId,
            ]),
        ]);
    }

    // $form is reloaded from the session and revalidated
    protected function handleInit(BridgeFormModel&ExtractDataInterface $form, string $confirmationId): Output
    {
        // Modal content for the AJAX dialog widget; docs/dialog.md has the full example.
        $content = (string) $this->renderPartial('_delete-content', [
            'articleId' => $this->currentRoute->getArgument('id'),
            'confirmationId' => $confirmationId,
        ])->getBody();

        return new Output(OutputType::Json, [
            ...AureliaCommunication::dialog(DialogAction::Keep),
            ...AureliaCommunication::dialogContent($content),
        ]);
    }
}
```

## Architecture

| Layer | Class | Adds | Use it when |
|---|---|---|---|
| 1 | `AbstractHandler` | render / json / redirect / download / AJAX helpers / view resolution | You write a handler that does its own `handle()` |
| 2 | `AbstractPipelineHandler` | 4-step pipeline + `Output` DTO + `CurrentRoute` | You want a structured handler with setup/process/output separation |
| 3 | `Dialog\AbstractDialogInit` + `AbstractDialogConfirm` | Session-backed AJAX submit/confirm flow | You build a confirmation dialog (delete, validate, two-step action) |

Each layer extends the previous one. Pick the lowest layer that fits.

## Configuration

```php
use Blackcube\Handler\HandlerConfig;

new HandlerConfig(
    handlerNamespacePrefix: 'App\\Handlers\\',
    viewsAlias:             '@src/Views',
    layoutAlias:            '@src/Views/Layouts/main.php',
    debug:                  false,
);
```

| Property | Default | Used by |
|---|---|---|
| `handlerNamespacePrefix` | `App\\Handlers\\` | `extractViewPath()` to map a handler class to a view subdirectory |
| `viewsAlias` | `@src/Views` | `render()` / `renderPartial()` to set the view path |
| `layoutAlias` | `@src/Views/Layouts/main.php` | `render()` to apply the layout |
| `debug` | `false` | Exposed as `$this->debug` for handlers that branch on it |

Register a single `HandlerConfig` instance in your DI container; every handler receives it.

## Documentation

| Topic | File |
|---|---|
| Overview and contents | [docs/index.md](docs/index.md) |
| Installation and DI wiring | [docs/installation.md](docs/installation.md) |
| Three-layer architecture | [docs/architecture.md](docs/architecture.md) |
| `AbstractHandler` reference | [docs/abstract-handler.md](docs/abstract-handler.md) |
| `AbstractPipelineHandler` reference | [docs/pipeline-handler.md](docs/pipeline-handler.md) |
| Dialog handlers reference | [docs/dialog.md](docs/dialog.md) |

## Let's be honest

**Coupled to Yii3.**

The base handler depends on `WebViewRenderer`, `Aliases`, `UrlGeneratorInterface` from Yii3. It is not framework-agnostic. If you build on Slim or Laravel without the Yii3 view layer, this package is not for you.

**Opinionated view resolution.**

The view resolution convention (handler namespace → view subdirectory under `viewsAlias`) is opinionated. It works very well when you keep handlers and views in mirrored folders, and not at all if you don't. The behavior is overridable but you will be fighting the defaults.

**The Dialog layer drags Bleet and BridgeFormModel.**

`Dialog\AbstractDialogInit` and `AbstractDialogConfirm` reference `AureliaCommunication`, `DialogAction`, `UiLevel` from `blackcube/bleet` and `BridgeFormModel` from `blackcube/bridge-form-model`. They are declared as `suggest`, but you cannot use the Dialog layer without installing them. If you don't use Bleet, ignore this layer entirely.

**No tests yet.**

The package ships without a test suite. Coverage is on the roadmap.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>
