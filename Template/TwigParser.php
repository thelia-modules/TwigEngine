<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TwigEngine\Template;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\ParserTemplateTrait;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Lang;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

/**
 * Class TwigParser.
 *
 * @author Alexandre Nozière - anoziere@openstudio.fr
 */
#[AutoconfigureTag('thelia.parser.template')]
class TwigParser implements ParserInterface
{
    use ParserTemplateTrait;

    public const FALLBACK_DEFAULT_THEME_NAME = 'default';

    private array $templateDirectories = [];

    public function __construct(
        private readonly Environment $twig,
        #[Autowire(service: 'twig.loader.native_filesystem')]
        private readonly FilesystemLoader $loader,
        private readonly ParserContext $parserContext,
        private readonly string $env = 'prod',
        private readonly bool $debug = false
    ) {
    }

    public function render($realTemplateName, array $parameters = [], $compressOutput = true): string
    {
        if (!str_ends_with($realTemplateName, '.'.$this->getFileExtension())) {
            $realTemplateName = (string) pathinfo($realTemplateName, \PATHINFO_FILENAME);
            $realTemplateName .= '.'.$this->getFileExtension();
        }

        $request = $this->getRequest();
        $session = null;
        if (null === $request) {
            $lang = Lang::getDefaultLanguage();
        } else {
            /** @var Session $session $lang */
            $session = $request->getSession();
            $lang = $session->getLang();
        }

        $parameters = array_merge($parameters, [
            'locale' => $lang->getLocale(),
            'lang_code' => $lang->getCode(),
            'lang_id' => $lang->getId(),
            'current_url' => $request?->getUri(),
            'app' => (object) [
                'environment' => $this->env,
                'request' => $this->getRequest(),
                'session' => $session,
                'debug' => $this->debug,
            ],
        ]);
        foreach ($this->parserContext as $variableName => $variableValue) {
            $this->assign($variableName, $variableValue);
        }
        foreach ($parameters as $variableName => $variableValue) {
            $this->assign($variableName, $variableValue);
        }

        return $this->twig->render($realTemplateName, $parameters);
    }

    public function supportTemplateRender(string $templatePath, ?string $templateName): bool
    {
        if ($templateName === null) {
            $templateName = 'index';
        }
        if (!str_ends_with($templatePath, DS)) {
            $templatePath .= DS;
        }

        return file_exists($templatePath.$templateName.'.'.$this->getFileExtension());
    }

    public function getFileExtension(): string
    {
        return 'html.twig';
    }

    public function renderString($templateText, array $parameters = [], $compressOutput = true): string
    {
        return $this->twig->render($templateText, $parameters);
    }

    /**
     * @throws LoaderError
     * @throws \Exception
     */
    public function setTemplateDefinition(TemplateDefinition|string $templateDefinition, $fallbackToDefaultTemplate = false): void
    {
        if (\is_string($templateDefinition)) {
            $templateDefinition = new TemplateDefinition($templateDefinition, TemplateDefinition::FRONT_OFFICE);
        }

        $this->templateDefinition = $templateDefinition;
        $this->fallbackToDefaultTemplate = $fallbackToDefaultTemplate;
        $type = $templateDefinition->getType();

        $this->addCurrentTemplateWithParent($templateDefinition);
        $this->addModulesTemplatesDirectories($templateDefinition, $type);
        $this->addFallbackDefaultModulesTemplatesDirectories($type, $fallbackToDefaultTemplate);
    }

    /**
     * @throws LoaderError
     */
    private function addCurrentTemplateWithParent(TemplateDefinition $templateDefinition): void
    {
        $this->loader->addPath($templateDefinition->getAbsolutePath());
        foreach ($templateDefinition->getParentList() as $parentTemplateDefinition) {
            $this->loader->addPath($parentTemplateDefinition->getAbsolutePath());
        }
    }

    /**
     * @throws LoaderError
     */
    private function addModulesTemplatesDirectories(
        TemplateDefinition $templateDefinition,
        int $type
    ): void {
        $templateDefinitionsWithParent = ['' => $templateDefinition] + $templateDefinition->getParentList();
        foreach ($templateDefinitionsWithParent as $templateDefinitionWithParent) {
            if (!isset($this->templateDirectories[$type][$templateDefinitionWithParent->getName()])) {
                continue;
            }
            foreach ($this->templateDirectories[$type][$templateDefinitionWithParent->getName()] as $directory) {
                $this->loader->addPath($directory);
            }
        }
    }

    /**
     * @throws LoaderError
     */
    private function addFallbackDefaultModulesTemplatesDirectories(
        int $type,
        bool $fallbackToDefaultTemplate
    ): void {
        if (false === $fallbackToDefaultTemplate) {
            return;
        }
        if (!isset($this->templateDirectories[$type][self::FALLBACK_DEFAULT_THEME_NAME])) {
            return;
        }
        foreach ($this->templateDirectories[$type][self::FALLBACK_DEFAULT_THEME_NAME] as $key => $directory) {
            if ([] !== $this->loader->getPaths($key)) {
                continue;
            }
            $this->loader->addPath($directory);
        }
    }

    public function assign($variable, $value = null): void
    {
        $this->twig->addGlobal($variable, $value);
    }

    public static function getDefaultPriority(): int
    {
        return 10;
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
