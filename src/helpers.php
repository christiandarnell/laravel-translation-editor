<?php
if (!function_exists('__te')) {
	function __te($key, array $replace = [], $locale = null)
	{
		return app('translation.editor')->get($key, $replace, $locale);
	}
}

if (!function_exists('__route')) {
	function __route($key, $attribute = null)
	{
		$replace = [];
		$locale = null;
		$rte = app('translation.editor')->getTranslation($key, $replace, $locale);
		$attr = '';
		if ($attribute) {
			// Remove last char from string (the ")
			$attr = '" ' . substr($attribute, 0, -1);
		}
		return Route::has($rte) ? route($rte) : url($rte . $attr);
	}
}
