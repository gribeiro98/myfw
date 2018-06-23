<?php

namespace Core;


class View extends Tokenizer
{
    private $layout;
    private $blocks = [];

    public function __construct()
    {
        $this->addRule('/{ ?\$([\w\d]+) ?= ?(.*) ?}/', '<?php $$1 = $2 ?>');
        $this->addRule('/{ ?\$([\w\d]+) ?}/', '<?= $$1 ?>');
        $this->addRule('/{ ?\$([\w\d]+)(\+\+|\-\-) ?}/', '<?php $$1$2 ?>');
        $this->addRule('/@if ?\((.*)\)/', '<?php if($1): ?>');
        $this->addRule('/@elseif ?\((.*)\)/', '<?php elseif($1): ?>');
        $this->addRule('/@else/', '<?php else: ?>');
        $this->addRule('/@for ?\((.*); ?(.*); ?(.*)\)/', '<?php for($1 ; $2; $3): ?>');
        $this->addRule('/@foreach ?\((.*) as (.*)( ?=> ?.*)?\)/', '<?php foreach($1 as $2 $3): ?>');
        $this->addRule('/@while ?\((.*)\)/', '<?php while($1): ?>');
        $this->addRule('/@end(if|foreach|for|while)/', '<?php end$1 ?>');

        $this->setLayoutRule("/@extends ?\('(.*)'\)/");
        $this->setSectionRule("/@section ?\('(.*)'\)/", '/@endsection/');
        $this->setConstructRule("/@yield ?\('(.*)'\)/");
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function setLayout(string $view): void
    {
        $file = fopen($view, 'r');

        while (!feof($file)) {
            $line = fgets($file);

            if (preg_match($this->getLayoutRule(), $line, $matches)) {
                $this->layout = __DIR__ . "/../app/views/layout/{$matches[1]}.phtml";
                break;
            }
        }

        fclose($file);
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function compile(string $view): string
    {
        $tempView = $view;

        foreach ($this->patterns as $pattern) {
            $tempView = preg_replace(array_keys($pattern), array_values($pattern), $tempView);
        }

        return $tempView;
    }

    public function setBlocks(string $view): void
    {
        $file = fopen($view, 'r');

        $sectionName = '';
        $contentBlocks = [];
        while (!feof($file)) {
            $line = fgets($file);

            if (preg_match($this->getSectionRuleByKey('openingTag'), $line, $matches)) {
                $sectionName = $matches[1];
            }

            $contentBlocks[$sectionName] .= $line;
            
            if (preg_match($this->getSectionRuleByKey('closingTag'), $line, $matches)) {
                $sectionName = '';
            }
        }

        $contentBlocks = array_filter($contentBlocks, function ($key) {
            return !empty($key);
        }, ARRAY_FILTER_USE_KEY);

        $newBlocks = [];
        foreach ($contentBlocks as $blockName => $block) {
            preg_match("/@section\('(.*)'\)((.|\n)*)@endsection/", $block, $matches);
            $newBlocks[$blockName] = trim($matches[2]);
        }

        fclose($file);
        $this->blocks = $newBlocks;
    }

    public function compileLayout(string $layoutName): string
    {
        $file = fopen($layoutName, 'r');
        $result = '';
        while (!feof($file)) {
            $line = fgets($file);

            preg_match($this->getConstructRule(), $line, $matches);
            if (array_key_exists($matches[1], $this->getBlocks())) {
                $line = preg_replace($this->getConstructRule(), $this->getBlocks()[$matches[1]], $line);
            } else {
                $line = preg_replace($this->getConstructRule(), "", $line);
            }

            $result .= $line;
        }
        fclose($file);
        return $result;
    }

    public function render(string $view, array $data = []): void
    {

        if (!empty($data)) {
            extract($data);
        }

        $view = __DIR__ . "/../app/views/{$view}.phtml";
        $this->setLayout($view);

        if ($this->layout != null) {
            $this->setBlocks($view);
            $content = $this->compile($this->compileLayout($this->getLayout()));
        } else {
            $viewContent = file_get_contents($view);
            $content = $this->compile($viewContent);
        }

        eval(' ?> ' . $content . '<?php ');
    }

}