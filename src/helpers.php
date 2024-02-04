<?php
if (!function_exists('__te')) {
	function __te($key, array $replace = [], $locale = null)
	{
		return app('translation.editor')->get($key, $replace, $locale);
	}
}

if (!function_exists('__route')) {
	function __route($key)
	{
		$replace = [];
		$locale = null;
		$rte = app('translation.editor')->getTranslation($key, $replace, $locale);
		return Route::has($rte) ? route($rte) : url($rte . '" target="_blank');
	}
}
