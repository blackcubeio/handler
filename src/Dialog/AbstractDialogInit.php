<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Handler\Dialog;

use Blackcube\Bleet\Enums\DialogAction;
use Blackcube\Bleet\Enums\UiLevel;
use Blackcube\Bleet\Helper\AureliaCommunication;
use Blackcube\BridgeFormModel\BridgeFormModel;
use Blackcube\BridgeFormModel\Interfaces\ExtractDataInterface;
use Blackcube\Handler\AbstractPipelineHandler;
use Blackcube\Handler\HandlerConfig;
use Blackcube\Handler\Output;
use Blackcube\Handler\OutputType;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * AJAX dialog — submit (validate + session) + init (load session + open modal).
 * Pipeline:
 *  - submit mode (no confirmationId): load body params into the form, validate, store extractData() in session,
 *    return modalUrl JSON
 *  - init mode (confirmationId in route): load the session data into the form, revalidate, render modal content
 *
 * Concrete classes implement:
 * - getSessionPrefix()
 * - createForm()
 * - handleSubmit($form, $confirmationId) — returns Output JSON (modalUrl)
 * - handleInit($form, $confirmationId) — renders modal content, returns Output JSON
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class AbstractDialogInit extends AbstractPipelineHandler
{
    protected (BridgeFormModel&ExtractDataInterface)|null $form = null;
    protected ?string $confirmationId = null;

    public function __construct(
        LoggerInterface $logger,
        HandlerConfig $config,
        WebViewRenderer $viewRenderer,
        ResponseFactoryInterface $responseFactory,
        JsonResponseFactory $jsonResponseFactory,
        UrlGeneratorInterface $urlGenerator,
        Aliases $aliases,
        CurrentRoute $currentRoute,
        protected SessionInterface $session,
    ) {
        parent::__construct(
            logger: $logger,
            config: $config,
            viewRenderer: $viewRenderer,
            responseFactory: $responseFactory,
            jsonResponseFactory: $jsonResponseFactory,
            urlGenerator: $urlGenerator,
            aliases: $aliases,
            currentRoute: $currentRoute,
        );
    }

    abstract protected function getSessionPrefix(): string;

    abstract protected function createForm(): BridgeFormModel&ExtractDataInterface;

    abstract protected function handleSubmit(BridgeFormModel&ExtractDataInterface $form, string $confirmationId): Output;

    abstract protected function handleInit(BridgeFormModel&ExtractDataInterface $form, string $confirmationId): Output;

    protected function setup(): ?Output
    {
        $output = null;
        $this->confirmationId = $this->currentRoute->getArgument('confirmationId');
        $this->form = $this->createForm();

        if ($this->confirmationId !== null) {
            $sessionData = $this->session->get($this->getSessionPrefix() . $this->confirmationId);

            if ($sessionData === null) {
                $output = new Output(OutputType::Json, [
                    ...AureliaCommunication::dialog(DialogAction::Close),
                    ...AureliaCommunication::toast('Error', 'Session expired, please start again.', UiLevel::Danger),
                ]);
            } else {
                $this->form->load($sessionData, '');

                if ($this->form->validate() === false) {
                    $output = new Output(OutputType::Json, [
                        ...AureliaCommunication::dialog(DialogAction::Close),
                        ...AureliaCommunication::toast('Error', 'Invalid data.', UiLevel::Danger),
                    ]);
                }
            }
        } else {
            $bodyParams = $this->getBodyParams();
            if ($bodyParams !== null) {
                $this->form->load($bodyParams);
            }

            if ($this->form->validate() === false) {
                $output = new Output(OutputType::Json, [
                    ...AureliaCommunication::toast('Validation error', 'Please check the form fields.', UiLevel::Danger),
                ]);
            }
        }

        return $output;
    }

    protected function process(): Output
    {
        if ($this->confirmationId !== null) {
            $output = $this->handleInit($this->form, $this->confirmationId);
        } else {
            $output = $this->handleSubmit($this->form, $this->storeInSession($this->form));
        }

        return $output;
    }

    /**
     * Stores the validated form data under a new confirmation id and returns the id.
     */
    protected function storeInSession(BridgeFormModel&ExtractDataInterface $form): string
    {
        $confirmationId = bin2hex(random_bytes(16));
        $this->session->set($this->getSessionPrefix() . $confirmationId, $form->extractData());
        return $confirmationId;
    }
}
