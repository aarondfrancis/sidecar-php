<?php
ini_set('display_errors', '1');

error_reporting(E_ALL);

$autoloaders = [
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    file_exists($autoloader) && require $autoloader;
}

function handleException(Throwable $exception)
{
    $exception = json_encode([
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'traceAsString' => $exception->getTraceAsString(),
        'type' => get_class($exception)
    ]);

    echo "\n__START_EXCEPTION__{$exception}__END_EXCEPTION__";
    exit(1);
}

$options = getopt($short = '', [
    'file::',
    'closure::'
]);

if (array_key_exists('file', $options)) {
    $options['closure'] = file_get_contents($options['file']);
    @unlink($options['file']);
}

$closure = $options['closure'];

if (!$closure) {
    handleException(new Exception('No closure defined.'));
}

try {
    $closure = unserialize(base64_decode($closure));
    $output = call_user_func($closure);
} catch (Throwable $e) {
    handleException($e);
}

$output = json_encode([
    'output' => $output
]);

echo "\n__START_FUNCTION_OUTPUT__{$output}__END_FUNCTION_OUTPUT__";
