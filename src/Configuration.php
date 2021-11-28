<?php

declare(strict_types = 1);

namespace Armin\Md2Pdf;

use Symfony\Component\OptionsResolver\OptionsResolver;

class Configuration implements \ArrayAccess
{
    private array $options;

    public function __construct(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'rootPath' => '.',
            'baseUrl' => '',
            'title' => '',
            'subtitle' => '',
            'author' => '',
            'styles' => '',
            'enableFooter' => true,
            'enableToc' => true,
            'tocHeadline' => 'Contents',
            'pageLabel' => 'Page',
            'syntaxHighlightingTheme' => 'vs',
            'autoLanguages' => ['yaml', 'html', 'css', 'js', 'php', 'md', 'txt'],
        ]);
        $resolver->setRequired('title')->setAllowedTypes('title', 'string');
        $resolver->setRequired('structure')->setAllowedTypes('structure', 'array'); // TODO: Check for possible child options
        $resolver->setRequired('output')->setAllowedTypes('output', 'string');
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->options);
    }

    /**
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->options[$offset] ?? null;
    }

    /**
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->options[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }
}
