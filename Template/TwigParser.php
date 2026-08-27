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
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\ParserTemplateTrait;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Domain\Localization\Service\LangService;
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

    /** @var array<string, array{list<string>, list<string>}> */
    private array $themeChainCache = [];

    /**
     * The variables handed over through assign(), merged into every render.
     *
     * Twig globals only take new entries until the environment is initialized, i.e. until the
     * first render of the request. Anything assigned afterwards - the second email sent while
     * handling one request, a hook rendered in the middle of a page - would otherwise be lost
     * in silence, the template reading null where the caller set a value.
     *
     * @var array<string, mixed>
     */
    private array $assignedVariables = [];

    public function __construct(
        private readonly Environment $twig,
        #[Autowire(service: 'twig.loader.native_filesystem')]
        private readonly FilesystemLoader $loader,
        private readonly ParserContext $parserContext,
        private readonly LangService $langService,
        private readonly string $env = 'prod',
        private readonly bool $debug = false
    ) {
    }

    public function render($realTemplateName, array $parameters = [], $compressOutput = true): string
    {
        $realTemplateName = $this->resolveTemplateName($realTemplateName);

        if (!$this->loader->exists($realTemplateName)) {
            // Same contract as the Smarty parser: a missing template raises a
            // ResourceNotFoundException (which callers such as Message catch to fall back),
            // rather than a raw Twig LoaderError.
            throw new ResourceNotFoundException(sprintf('Template file %s cannot be found.', $realTemplateName));
        }

        $lang = $this->langService->getLang();
        $request = $this->getRequest();

        $parameters = array_merge($parameters, [
            'locale' => $lang?->getLocale(),
            'lang_code' => $lang?->getCode(),
            'lang_id' => $lang?->getId(),
            'current_url' => $request?->getUri(),
            'app' => (object) [
                'environment' => $this->env,
                'request' => $request,
                'session' => $request?->hasSession() ? $request->getSession() : null,
                'debug' => $this->debug,
            ],
        ]);
        foreach ($this->parserContext as $variableName => $variableValue) {
            $this->assign($variableName, $variableValue);
        }
        foreach ($parameters as $variableName => $variableValue) {
            $this->assign($variableName, $variableValue);
        }

        return $this->twig->render($realTemplateName, $this->withAssignedVariables($parameters));
    }

    public function supportTemplateRender(string $templatePath, ?string $templateName): bool
    {
        if ($templateName === null) {
            $templateName = 'index';
        }

        foreach ($this->getTemplateSearchDirectories($templatePath) as $directory) {
            foreach ($this->getFileExtensions() as $fileExtension) {
                if (file_exists($directory.DS.$templateName.'.'.$fileExtension)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The directories a template may be loaded from, in the order setTemplateDefinition()
     * fills the Twig loader: the theme itself, the themes it inherits from, then the
     * directories the modules contribute to each of them and to the default theme.
     *
     * Looking only at the theme directory would make the parser claim it cannot render a
     * view that a module ships - a module template is a default the theme may override,
     * not a file the theme has to provide.
     *
     * @return list<string>
     */
    private function getTemplateSearchDirectories(string $templatePath): array
    {
        $templatePath = rtrim($templatePath, DS);

        $type = array_search(basename(\dirname($templatePath)), TemplateDefinition::$standardTemplatesSubdirs, true);

        if (!\is_int($type)) {
            return [$templatePath];
        }

        [$directories, $themeNames] = $this->getThemeChain($templatePath, $type);

        // The directories modules contribute to the default theme: every caller resolving a
        // parser sets the template definition with the fallback to the default theme enabled,
        // so setTemplateDefinition() hands them to the loader too. A module shipping a view
        // for the default theme therefore renders under any theme, and is claimed as such.
        $themeNames[] = self::FALLBACK_DEFAULT_THEME_NAME;

        foreach (array_unique($themeNames) as $themeName) {
            foreach ($this->templateDirectories[$type][$themeName] ?? [] as $moduleTemplateDirectory) {
                $directories[] = rtrim($moduleTemplateDirectory, DS);
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * The theme and the themes it inherits from, as directories and as names. Resolving the
     * parents reads and parses the theme descriptors, so the chain is resolved once per theme:
     * a template lookup happens on every rendered view, and on every candidate parser.
     *
     * @return array{list<string>, list<string>}
     */
    private function getThemeChain(string $templatePath, int $type): array
    {
        if (isset($this->themeChainCache[$templatePath])) {
            return $this->themeChainCache[$templatePath];
        }

        $directories = [$templatePath];
        $themeNames = [basename($templatePath)];

        try {
            foreach ((new TemplateDefinition($themeNames[0], $type))->getParentList() ?? [] as $parentTemplateDefinition) {
                $directories[] = rtrim($parentTemplateDefinition->getAbsolutePath(), DS);
                $themeNames[] = $parentTemplateDefinition->getName();
            }
        } catch (\Throwable) {
            // Reading a theme descriptor goes through Tlog, hence the Propel models, so it
            // does more than read a file: outside a booted kernel it fails outright. A theme
            // whose descriptor cannot be read simply has no parent chain to walk - its own
            // directory and the modules registered for it are all there is to search.
        }

        return $this->themeChainCache[$templatePath] = [$directories, $themeNames];
    }

    public function getFileExtension(): string
    {
        return 'html.twig';
    }

    /**
     * Extensions handled by the Twig parser: HTML templates (front, back office, HTML
     * emails, PDF) and text templates (the .txt version of an email).
     *
     * @return list<string>
     */
    public function getFileExtensions(): array
    {
        return ['html.twig', 'txt.twig'];
    }

    /**
     * Map a requested template name to its Twig file, preserving the logical extension:
     * a name ending in ".txt" resolves to a ".txt.twig" file, anything else (".html", no
     * extension, …) to a ".html.twig" file. A name already carrying a handled extension is
     * left untouched.
     */
    private function resolveTemplateName(string $realTemplateName): string
    {
        foreach ($this->getFileExtensions() as $fileExtension) {
            if (str_ends_with($realTemplateName, '.'.$fileExtension)) {
                return $realTemplateName;
            }
        }

        $twigExtension = str_ends_with($realTemplateName, '.txt') ? 'txt.twig' : 'html.twig';

        return pathinfo($realTemplateName, \PATHINFO_FILENAME).'.'.$twigExtension;
    }

    public function renderString($templateText, array $parameters = [], $compressOutput = true): string
    {
        // Render the given string as an inline template (Smarty's renderString contract),
        // e.g. a mail subject or a back-office-edited message body stored in database.
        // The previous implementation treated the string as a template name, which only
        // worked for callers that happened to pass a name; use createTemplate() so an actual
        // template source is compiled and rendered against the assigned globals + parameters.
        // A null/empty source (e.g. a message with no subject in database) renders to an
        // empty string, as the Smarty parser does, rather than raising a TypeError.
        if (null === $templateText || '' === $templateText) {
            return '';
        }

        return $this->twig->createTemplate($templateText)->render($this->withAssignedVariables($parameters));
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
        $variables = \is_array($variable) ? $variable : [$variable => $value];

        foreach ($variables as $variableName => $variableValue) {
            $this->assignedVariables[$variableName] = $variableValue;

            try {
                // Registered as a global too while the environment still accepts them, so the
                // variable also reaches the places a render context does not: macros and
                // templates included with "only".
                $this->twig->addGlobal($variableName, $variableValue);
            } catch (\LogicException) {
                // The environment is already initialized - a hook rendering a template while
                // the page is still rendering, a second email in the same request. The
                // variable is carried by the render context instead, see withAssignedVariables().
            }
        }
    }

    /**
     * The render context: what was assigned so far, the parameters of this render winning
     * over it, as they would over a global.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function withAssignedVariables(array $parameters): array
    {
        return array_merge($this->assignedVariables, $parameters);
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
