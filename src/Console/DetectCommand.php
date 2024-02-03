<?php

namespace ChristianDarnell\Translation\Editor\Console;

use ChristianDarnell\Translation\Editor\TranslationEditor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\DomCrawler\Crawler;

// Override class, se composer.json exclude-from-classmap
class DetectCommand extends Command
{
	/**
	 * @var array
	 */
	protected const DETECTION_REGEXES = [
		'/\s*(?P<line>.*\s(?P<context>(?:title|label|help|alt|placeholder|aria-label)="\s*(?P<text>[^"]+)\s*")[^\n]*)/',
		'/\s*(?P<line>.*(?P<context>>\s*(?P<text>[^<>\n]+?)\s*<\/)[^\n]*)/',
	];
	protected const ATTRIBUTES = ['title', 'label', 'help', 'alt', 'placeholder'];

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'translate:detect {--l|locale=} {target?*}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Detect text without translation variables in views';

	/**
	 * @var \Exolnet\Translation\Editor\TranslationEditor
	 */
	protected $translationEditor;

	/**
	 * @param \Exolnet\Translation\Editor\TranslationEditor $translationEditor
	 */
	public function __construct(TranslationEditor $translationEditor)
	{
		parent::__construct();

		$this->translationEditor = $translationEditor;
	}

	/**
	 * Execute the console command.
	 *
	 * @param \Symfony\Component\Finder\Finder $finder
	 * @return void
	 */
	public function handle(Finder $finder)
	{
		$targets = $this->getTargets();

		$finder->files()->in($targets)->name('*.php');

		foreach ($finder as $file) {
			$this->info('>> ' . $file->getRelativePathname());

			if (!$this->confirm('Would you like to translate this file?')) {
				continue;
			}
			$this->processFile($file);
			$this->processFileDom($file);
		}
	}

	/**
	 * @return array
	 */
	protected function getTargets()
	{
		if ($target = $this->argument('target')) {
			return $target;
		}

		return [resource_path('views')];
	}

	/**
	 * @param $content
	 * @return \Illuminate\Support\Collection|string[]
	 */
	protected function extractTexts($content)
	{
		$content = preg_replace('/@?{{.+?}}/', '', $content);
		$content = preg_replace('/{{{.+?}}}/', '', $content);
		$content = preg_replace('/{!!.+?!!}/', '', $content);
		$content = preg_replace('/{{--.+?--}}/', '', $content);
		$content = preg_replace('/<\?.+?\?>/', '', $content);
		$content = preg_replace('/<!--(.+?)-->/', '', $content);

		$texts = collect();

		foreach (static::DETECTION_REGEXES as $detectionRegex) {
			preg_match_all($detectionRegex, $content, $matches, PREG_SET_ORDER);

			$texts = $texts->merge($matches);
		}

		return $texts->filter(function ($value) {
			if (!trim($value['text'])) {
				return false;
			}

			return !Str::contains($value['text'], ['@', '__']);
		});
	}

	/**
	 * @param string $content
	 * @param array $text
	 * @param string $replacement
	 * @return string
	 */
	protected function replaceTextInContext($content, array $text, $replacement)
	{
		return str_replace(
			$text['context'],
			str_replace($text['text'], $replacement, $text['context']),
			$content
		);
	}

	/**
	 * @param \Symfony\Component\Finder\SplFileInfo $file
	 */
	protected function processFileDom(SplFileInfo $file)
	{
		$html = $file->getContents();
		$crawler = new Crawler($html, useHtml5Parser: true);
		$root = $crawler->filter('x-layout');

		if (!$root->count()) {
			return;
		}
		$this->processDOMNode($root);

		$content = $crawler->filter('body')->html();

		// Remove the HTML entity encoding
		$content = html_entity_decode(urldecode($content), ENT_HTML5, 'UTF-8');

		file_put_contents($file->getRealPath(), $content);
	}

	private function processDOMNode(Crawler $domNode)
	{
		if (strcmp($domNode->nodeName(), 'script') == 0) {
			$this->info('script found');
			return true;
		}
		foreach (DetectCommand::ATTRIBUTES as $attr) {
			if (!is_null($domNode->attr($attr))) {
				$text['text'] = $domNode->attr($attr);
				$text['line'] = $text['context'] =	$domNode->outerHtml();
				$target = $this->replaceTextInContext($domNode->outerHtml(), $text, "{{ __('" . $domNode->attr($attr) . "')  }}");

				if ($this->storeVariabel($text, $target)) {
					$domNode->getNode(0)->setAttribute($attr, "{{ __('" . $domNode->attr($attr) . "')  }}");
				}
			}
		}

		return $domNode->children()->each(function (Crawler $node): bool {
			if ($node->children()->count()) {
				if ($this->processDOMNode($node)) {
					$text['text'] = $node->text(false);
					$text['line'] = $text['context'] =	$node->html();
					$target = $this->replaceTextInContext($node->html(), $text, "@__te('" . $node->text(false) . "')");

					if ($this->storeVariabel($text, $target)) {
						$node->getNode(0)->nodeValue = $target;
					}
				}
				return false;
			} else {
				if (strlen($node->text())) {
					$text['text'] = $node->text(false);
					$text['line'] = $text['context'] = $node->html();
					$target = $this->replaceTextInContext($node->html(), $text, "@__te('" . $node->text(false) . "')");

					if ($this->storeVariabel($text, $target)) {
						$node->getNode(0)->nodeValue = $target;
					}
				}
				return true;
			}
		});
	}

	private function storeVariabel(array $text, $target)
	{
		if (!strlen($text['text'])) {
			return false;
		}
		if (str_contains($text['line'], '@__te')) {
			return false;
		}
		if (str_contains($text['line'], '__(')) {
			return false;
		}
		$this->info('Found: ' . $text['text']);
		$this->comment('+ ' . $target);
		$this->comment('- ' . $text['line']);

		$variable = $this->anticipate('Variable name (DOM)', [$text['text']], 'skip');
		if ($variable === 'skip') {
			return false;
		}
		$this->translationEditor->storeTranslation($variable, '', $this->getLocale());
		return true;
	}

	/**
	 * @param \Symfony\Component\Finder\SplFileInfo $file
	 */
	protected function processFile(SplFileInfo $file)
	{
		$content = $originalContent = $file->getContents();
		$texts   = $this->extractTexts($content);

		if (count($texts) === 0) {
			return;
		}
		/*
		$this->info('>> ' . $file->getRelativePathname());

		if (!$this->confirm('Would you like to translate this file?')) {
			return;
		}
*/
		$attributes = ['title', 'label', 'help'];
		foreach ($texts as $text) {

			$target = $this->replaceTextInContext($text['line'], $text, '@__te(\'variable\')');
			if (Str::contains($text['line'], $attributes)) {
				$target = $this->replaceTextInContext($text['line'], $text, '{{ __(\'variable\') }}');
			}

			$this->info('Found: ' . $text['text']);
			$this->comment('+ ' . $target);
			$this->comment('- ' . $text['line']);

			$possibleVariables = $this->findVariablesForText($text['text']);
			$variable = 'create a new variable';

			if (!empty($possibleVariables)) {
				$shouldSkip = $this->confirm(
					'Some variables already exists for this text, would you like to use one of them?',
					true
				);

				if ($shouldSkip) {
					$possibleVariables[] = 'create a new variable';
					$possibleVariables[] = 'skip';
					$variable = $this->choice('Available variables', $possibleVariables, 0);
				}
			}

			if ($variable === 'create a new variable') {
				//$definedNames = $this->getDefinedNames();
				$definedNames = [$text['text']];
				$variable = $this->anticipate('Variable name', $definedNames, 'skip');
			}


			if ($variable === 'skip') {
				continue;
			}

			$this->translationEditor->storeTranslation($variable, '', $this->getLocale());


			if (Str::contains($text['line'], $attributes)) {
				$content = $this->replaceTextInContext($content, $text, '{{ __(\'' . $variable . '\') }}');
			} else {
				$content = $this->replaceTextInContext($content, $text, '@__te(\'' . $variable . '\')');
			}
			file_put_contents($file->getRealPath(), $content);
		}
	}

	/**
	 * @return string
	 */
	protected function getLocale()
	{
		return $this->option('locale') ?: $this->laravel->getLocale();
	}

	/**
	 * @param string $text
	 * @return array
	 */
	protected function findVariablesForText($text)
	{
		return $this->translationEditor->findVariablesForText($text, $this->getLocale());
	}

	/**
	 * @return array
	 */
	protected function getDefinedNames()
	{
		return $this->translationEditor->getAllDefinedNames($this->getLocale());
	}
}
