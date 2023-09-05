<?php

namespace Owl\Bundle\TinymceBundle\Twig\Extension;

use Owl\Bundle\TinymceBundle\Helper\LocaleHelper;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * TinymceExtension.
 *
 * @author naydav <web@naydav.com>
 */
class TinymceExtension extends AbstractExtension
{
    /**
     * @var ContainerInterface $container
     */
    protected $container;

    /**
     * Asset Base Url.
     *
     * Used to over ride the asset base url (to not use CDN for instance)
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * @var Packages
     */
    private $packages;

    /**
     * @param ContainerInterface $container
     * @param Packages           $packages
     */
    public function __construct(ContainerInterface $container, Packages $packages)
    {
        $this->container = $container;
        $this->packages = $packages;
    }

    /**
     * @param string $id The service identifier
     *
     * @return object The associated service
     */
    public function getService($id)
    {
        return $this->container->get($id);
    }

    /**
     * Get parameters from the service container.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return TwigFunction[] An array of functions
     *
     * @psalm-return array{tinymce_init: TwigFunction}
     */
    public function getFunctions(): array
    {
        return [
            'tinymce_init' => new TwigFunction(
                'tinymce_init',
                [$this, 'tinymceInit'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    /**
     * TinyMce initializations
     *
     * @param array $options
     *
     * @return string
     */
    public function tinymceInit($options = []): string
    {
        $config = $this->getParameter('tinymce.config');
        $config = array_merge_recursive($config, $options);

        $this->baseUrl = $config['base_url'] ?? null;

        // Asset package name
        $assetPackageName = $config['asset_package_name'] ?? null;
        unset($config['asset_package_name']);

        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->packages;

        // Get path to tinymce script for the jQuery version of the editor
        if ($config['tinymce_jquery']) {
            $config['jquery_script_url'] = $assets->getUrl(
                $this->baseUrl.'bundles/tinymce/vendor/tinymce/tinymce.jquery.min.js',
                $assetPackageName
            );
        }

        // Get local button's image
        foreach ($config['tinymce_buttons'] as &$customButton) {
            if ($customButton['image']) {
                $customButton['image'] = $this->getAssetsUrl($customButton['image']);
            } else {
                unset($customButton['image']);
            }

            if ($customButton['icon']) {
                $customButton['icon'] = $this->getAssetsUrl($customButton['icon']);
            } else {
                unset($customButton['icon']);
            }
        }

        // Update URL to external plugins
        foreach ($config['external_plugins'] as &$extPlugin) {
            $extPlugin['url'] = $this->getAssetsUrl($extPlugin['url']);
        }

        // If the language is not set in the config...
        if (!isset($config['language']) || empty($config['language'])) {
            // get it from the request
            $config['language'] = $this->container->get('request_stack')->getCurrentRequest()->getLocale();
        }

        $config['language'] = LocaleHelper::getLanguage($config['language']);

        $langDirectory = __DIR__.'/../../Resources/public/vendor/tinymce/langs/';

        // A language code coming from the locale may not match an existing language file
        if (!file_exists($langDirectory.$config['language'].'.js')) {
            unset($config['language']);
        }

        if (isset($config['language']) && $config['language']) {
            // TinyMCE does not allow to set different languages to each instance
            foreach ($config['theme'] as $themeName => $themeOptions) {
                $config['theme'][$themeName]['language'] = $config['language'];
            }
        }

        if (isset($config['theme']) && $config['theme']) {
            // Parse the content_css of each theme so we can use 'asset[path/to/asset]' in there
            foreach ($config['theme'] as $themeName => $themeOptions) {
                if (isset($themeOptions['content_css'])) {
                    // As there may be multiple CSS Files specified we need to parse each of them individually
                    $cssFiles = $themeOptions['content_css'];
                    if (!\is_array($themeOptions['content_css'])) {
                        $cssFiles = explode(',', $themeOptions['content_css']);
                    }

                    foreach ($cssFiles as $idx => $file) {
                        $cssFiles[$idx] = $this->getAssetsUrl(trim($file)); // we trim to be sure we get the file without spaces.
                    }

                    // After parsing we add them together again.
                    $config['theme'][$themeName]['content_css'] = implode(',', $cssFiles);
                }
            }
        }

        $tinymceConfiguration = \preg_replace(
            [
                '/"file_browser_callback":"([^"]+)"\s*/',
                '/"file_picker_callback":"([^"]+)"\s*/',
                '/"paste_preprocess":"([^"]+)"\s*/',
            ],
            [
                'file_browser_callback:$1',
                'file_picker_callback:$1',
                '"paste_preprocess":$1',
            ],
            \json_encode($config)
        );

        return $this->getService('twig')->render(
            '@Tinymce/Script/init.html.twig',
            [
                'tinymce_config' => $tinymceConfiguration,
                'include_jquery' => $config['include_jquery'],
                'tinymce_jquery' => $config['tinymce_jquery'],
                'asset_package_name' => $assetPackageName,
                'base_url' => $this->baseUrl,
            ]
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     *
     * @psalm-return 'tinymce'
     */
    public function getName(): string
    {
        return 'tinymce';
    }

    /**
     * Get url from config string
     *
     * @param string $inputUrl
     *
     * @return string
     */
    protected function getAssetsUrl(string $inputUrl): string
    {
        $assets = $this->packages;

        $url = preg_replace('/^asset\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $url) {
            return $assets->getUrl($this->baseUrl.$url);
        }

        return $inputUrl;
    }
}
