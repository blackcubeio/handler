# handler — Layer 3: AJAX dialogs

## Prerequisites

- `blackcube/bleet` (`AureliaCommunication`, `DialogAction`, `UiLevel`), `blackcube/bridge-form-model`, `yiisoft/session`.
- The form extends `BridgeFormModel` and implements `Blackcube\BridgeFormModel\Interfaces\ExtractDataInterface` with `Blackcube\BridgeFormModel\Traits\ExtractDataTrait`.
- Every field the dialog carries has a validation rule: without one, `load()` does not hydrate it, neither from the POST nor from the session.

## The 3 exchanges

| # | Route | Class | Mode |
|---|---|---|---|
| 1 | no `confirmationId` argument (e.g. `POST /articles/{id}/delete`) | `AbstractDialogInit` subclass | submit |
| 2 | with `{confirmationId}` (e.g. `GET /articles/{id}/delete/{confirmationId}`) | the same `AbstractDialogInit` subclass | init |
| 3 | with `{confirmationId}` (e.g. `POST /articles/{id}/delete/{confirmationId}/confirm`) | `AbstractDialogConfirm` subclass | confirm |

- Both classes return the same `getSessionPrefix()` and build the same form (same scenario).
- Context (e.g. `{id}`) is read from the route; any other data is a field of the form.

## `AbstractDialogInit`

Constructor: the services of `AbstractPipelineHandler` + `protected SessionInterface $session`.

```php
abstract protected function getSessionPrefix(): string;
abstract protected function createForm(): BridgeFormModel&ExtractDataInterface;
abstract protected function handleSubmit(BridgeFormModel&ExtractDataInterface $form, string $confirmationId): Output;
abstract protected function handleInit(BridgeFormModel&ExtractDataInterface $form, string $confirmationId): Output;
```

`protected` properties: `$form` (`(BridgeFormModel&ExtractDataInterface)|null`), `$confirmationId` (`?string`, `null` in submit mode).

Submit mode:
1. `createForm()` → `load(getBodyParams())` (scope = form name) → `validate()`; invalid → `{"toast":{"title":"Validation error","content":"Please check the form fields.","color":"danger","duration":5000}}`, session untouched.
2. `storeInSession($form)`: `confirmationId` = `bin2hex(random_bytes(16))` (32 hex chars); `session[<prefix><confirmationId>] = $form->extractData()` (only the form data, e.g. `{"name":"Alice"}`; extra POST keys are not stored).
3. `handleSubmit($form, $confirmationId)` → typically `{modalUrl}`.

Init mode:
1. `createForm()`; read `session[<prefix><confirmationId>]`; missing → `{"action":"close","toast":{"title":"Error","content":"Session expired, please start again.","color":"danger","duration":5000}}`.
2. `load($data, '')` → `validate()`; invalid → `{"action":"close","toast":{"title":"Error","content":"Invalid data.",…}}`.
3. `handleInit($form, $confirmationId)` → the modal content. The session entry is kept.

## `AbstractDialogConfirm`

Constructor: same as `AbstractDialogInit`.

```php
abstract protected function getSessionPrefix(): string;
abstract protected function createForm(): BridgeFormModel&ExtractDataInterface;
abstract protected function handleConfirm(BridgeFormModel&ExtractDataInterface $form): Output;
```

1. Read `session[<prefix><confirmationId>]`; missing (or route without `confirmationId`) → "Session expired, please start again." + close.
2. Remove the session entry: a `confirmationId` is used once (a second confirm → "Session expired").
3. `createForm()` → `load($data, '')` → `validate()`; invalid → "Invalid data." + close (entry already removed).
4. `handleConfirm($form)`. The body of the confirm request is never read (a POST `Mallory` still gives `Alice`).

## Built-in messages

| Case | Class | `DialogAction` | Toast (title / content) |
|---|---|---|---|
| submit invalid | Init | none | `Validation error` / `Please check the form fields.` |
| session missing | Init, Confirm | `Close` | `Error` / `Session expired, please start again.` |
| session data invalid | Init, Confirm | `Close` | `Error` / `Invalid data.` |
