<?php
namespace Plugitify\muPlugin\Core;

/**
 * Best-effort syntax checks for browser-facing source files.
 *
 * This class deliberately never throws a validation failure back to the
 * writing tool. A file is already written before this class is called; the
 * result is informational and is returned to the model as part of the tool
 * output.
 */
final class SyntaxValidator
{
    /**
     * Run the parser that matches a file extension and return a short hint
     * suitable for appending to a write/edit tool response.
     */
    public static function hint(string $absolute, string $relative): string
    {
        $extension = strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            return '';
        }

        $language = self::language_for($extension);
        if ($language === null) {
            return '';
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            return ' Syntax check (' . $language . ') unavailable: the file could not be read after saving.';
        }

        try {
            $errors = self::parse($language, $contents);
        } catch (\RuntimeException $error) {
            return ' Syntax check (' . $language . ') unavailable: ' . self::clean_message($error->getMessage()) . '.';
        } catch (\Throwable $error) {
            return ' *** SYNTAX ERRORS (' . $language . ') in ' . $relative . ': '
                . self::clean_message($error->getMessage())
                . ' ***';
        }

        if ($errors === []) {
            return ' Syntax check (' . $language . '): no syntax errors found.';
        }

        return ' *** SYNTAX ERRORS (' . $language . ') in ' . $relative . ': '
            . implode(' | ', $errors)
            . ' ***';
    }

    private static function language_for(string $extension): ?string
    {
        if ($extension === 'html' || $extension === 'htm') {
            return 'HTML';
        }

        if ($extension === 'css') {
            return 'CSS';
        }

        if ($extension === 'js' || $extension === 'mjs' || $extension === 'cjs') {
            return 'JavaScript';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function parse(string $language, string $contents): array
    {
        if ($language === 'HTML') {
            if (!class_exists('\\Masterminds\\HTML5')) {
                throw new \RuntimeException('parser library is not installed (masterminds/html5)');
            }

            $parser = new \Masterminds\HTML5();
            $parser->loadHTML($contents);
            $errors = [];

            foreach ($parser->getErrors() as $error) {
                $message = self::error_message($error);

                // A plugin template can intentionally be an HTML fragment.
                // Missing <!DOCTYPE html> is not a syntax error in that case.
                if (stripos($message, 'No DOCTYPE specified') !== false) {
                    continue;
                }

                if ($message !== '') {
                    $errors[] = $message;
                }
            }

            return array_slice($errors, 0, 20);
        }

        if ($language === 'CSS') {
            if (!class_exists('\\Sabberworm\\CSS\\Parser')) {
                throw new \RuntimeException('parser library is not installed (sabberworm/php-css-parser)');
            }

            $settings = \Sabberworm\CSS\Settings::create()->beStrict();
            (new \Sabberworm\CSS\Parser($contents, $settings))->parse();

            return [];
        }

        if (!class_exists('\\Peast\\Peast')) {
            throw new \RuntimeException('parser library is not installed (mck89/peast)');
        }

        \Peast\Peast::latest($contents)->parse();

        return [];
    }

    /**
     * @param mixed $error
     */
    private static function error_message($error): string
    {
        if ($error instanceof \Throwable) {
            return self::clean_message($error->getMessage());
        }

        if (is_object($error) && method_exists($error, '__toString')) {
            return self::clean_message((string) $error);
        }

        if (is_scalar($error)) {
            return self::clean_message((string) $error);
        }

        return '';
    }

    private static function clean_message(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        return strlen($message) > 1000 ? substr($message, 0, 1000) . '…' : $message;
    }
}
