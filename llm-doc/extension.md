# handler — Extension

## Extension points

| Class | Member (`protected` unless stated) | Use |
|---|---|---|
| `AbstractHandler` | `getLayout()` | another layout per handler or handler family |
| | `getViewPath()` | another views directory for a handler family |
| | `extractViewPath(string $view)` | another view naming convention |
| | `render()`, `renderPartial()`, `renderJson()`, `redirect()`, `downloadContent()`, `downloadFile()`, `getBodyParams()`, `isAjax()`, `isAjaxify()` | override; `parent::` keeps the default |
| | promoted constructor properties (`$logger`, `$config`, `$viewRenderer`, `$responseFactory`, `$jsonResponseFactory`, `$urlGenerator`, `$aliases`), `$request`, `$debug` | available in subclasses |
| `AbstractPipelineHandler` | `setup()`, `setupMethod()`, `prepareOutputData()`, `download()` | pipeline hooks (no-op defaults) |
| | `output(Output $output)` | change the response of a type; `parent::output()` for the others |
| | `handle()` (public) | change the pipeline sequence |
| `Output` | non-final class | a subclass keeps its type through `with*()`; the withers call `new static($type, $params, $view)`: its constructor keeps the `(OutputType, array, ?string)` signature |
| `AbstractDialogInit` | `storeInSession(BridgeFormModel&ExtractDataInterface $form)` | change the id generation or the stored content |
| `AbstractDialogInit`, `AbstractDialogConfirm` | `setup()`, `process()`, `$form`, `$session`, `$confirmationId` (Init) | override a mode's sequence |

Application base handler: `abstract class AppHandler extends AbstractPipelineHandler` overriding e.g. `getLayout()` or `prepareOutputData()` for a whole handler family.

## Not extensible

| Element | Reason |
|---|---|
| `OutputType` | a PHP enum does not extend; another response kind goes through an `output()` override |
| `Output` properties | `private readonly`: a subclass reads them through the getters only |
| `Redirect` status in `output()` | always 302 (`AbstractPipelineHandler.php:105-108`); override `output()` or call `redirect(..., $status)` |
| `Download` disposition in `output()` | always `attachment`; override `output()` or call `downloadContent(..., ['attachment' => 'inline'])` |
| Dialog messages | hardcoded in `setup()`, no dedicated hook: override the whole `setup()` |
| `HandlerConfig` properties | `public readonly`: not modifiable after construction |
