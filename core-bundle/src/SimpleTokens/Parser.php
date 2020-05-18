<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\SimpleTokens;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Parser
{
    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var ExpressionLanguage
     */
    private $expressionLanguage;

    public function __construct(LoggerInterface $logger = null, ExpressionLanguage $expressionLanguage = null)
    {
        if (null === $expressionLanguage) {
            $expressionLanguage = new ExpressionLanguage();
        }

        // Disable `constant()` function for security reasons.
        $expressionLanguage->register(
            'constant',
            static function () {
                return "throw new \\InvalidArgumentException('Cannot use the constant() function in the expression for security reasons.');";
            },
            static function (): void {
                throw new \InvalidArgumentException('Cannot use the constant() function in the expression for security reasons.');
            }
        );

        $this->expressionLanguage = $expressionLanguage;
        $this->logger = $logger;
    }

    /**
     * Parse simple tokens.
     *
     * @param string $subject The string to be parsed
     * @param array  $data    The replacement data
     *
     * @throws \RuntimeException         If $subject cannot be parsed
     * @throws \InvalidArgumentException If there are incorrectly formatted if-tags
     *
     * @return string The converted string
     */
    public function parseTokens(string $subject, array $data): string
    {
        // Check if we can use the expression language or if legacy tokens have been used
        $canUseExpressionLanguage = $this->canUseExpressionLanguage($data);

        // The last item is true if it is inside a matching if-tag
        $stack = [true];

        // The last item is true if any if/elseif at that level was true
        $ifStack = [true];

        // Tokenize the string into tag and text blocks
        $tags = preg_split('/({[^{}]+})\n?/', $subject, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Parse the tokens
        $return = '';

        foreach ($tags as $tag) {
            // True if it is inside a matching if-tag
            $current = $stack[\count($stack) - 1];
            $currentIf = $ifStack[\count($ifStack) - 1];

            if (0 === strncmp($tag, '{if ', 4)) {
                $expression = $this->evaluateExpression(substr($tag, 4, -1), $data, $canUseExpressionLanguage);
                $stack[] = $current && $expression;
                $ifStack[] = $expression;
            } elseif (0 === strncmp($tag, '{elseif ', 8)) {
                $expression = $this->evaluateExpression(substr($tag, 8, -1), $data, $canUseExpressionLanguage);
                array_pop($stack);
                array_pop($ifStack);
                $stack[] = !$currentIf && $stack[\count($stack) - 1] && $expression;
                $ifStack[] = $currentIf || $expression;
            } elseif (0 === strncmp($tag, '{else}', 6)) {
                array_pop($stack);
                array_pop($ifStack);
                $stack[] = !$currentIf && $stack[\count($stack) - 1];
                $ifStack[] = true;
            } elseif (0 === strncmp($tag, '{endif}', 7)) {
                array_pop($stack);
                array_pop($ifStack);
            } elseif ($current) {
                $return .= $this->replaceTokens($tag, $data);
            }
        }

        // Throw an exception if there is an error
        if (1 !== \count($stack)) {
            throw new \RuntimeException('Error parsing simple tokens');
        }

        return $return;
    }

    private function replaceTokens(string $subject, array $data): string
    {
        // Replace tokens
        return preg_replace_callback(
            '/##([^=!<>\s]+?)##/',
            function (array $matches) use ($data) {
                if (!\array_key_exists($matches[1], $data)) {
                    if (null !== $this->logger) {
                        $this->logger->log(LogLevel::INFO, sprintf('Tried to parse unknown simple token "%s".', $matches[1]));
                    }

                    return '##'.$matches[1].'##';
                }

                return $data[$matches[1]];
            },
            $subject
        );
    }

    private function canUseExpressionLanguage(array $data): bool
    {
        foreach (array_keys($data) as $token) {
            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $token)) {
                @trigger_error('Using tokens that are not valid PHP variables has been deprecated and will no longer work in Contao 5.0. Falling back to legacy token parsing.', E_USER_DEPRECATED);

                return false;
            }
        }

        return true;
    }

    private function evaluateExpression(string $expression, array $data, bool $canUseExpressionLanguage): bool
    {
        if ($canUseExpressionLanguage) {
            try {
                return $this->expressionLanguage->evaluate($expression, $data);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException($e->getMessage(), 0, $e);
            }
        }

        // Legacy code
        if (!preg_match('/^([^=!<>\s]+) *([=!<>]+)(.+)$/s', $expression, $matches)) {
            return false;
        }

        [, $token, $operator, $value] = $matches;

        if (!\array_key_exists($token, $data)) {
            if (null !== $this->logger) {
                $this->logger->log(LogLevel::INFO, sprintf('Tried to evaluate unknown simple token "%s".', $token));
            }

            return false;
        }

        $tokenValue = $data[$token];

        // Normalize types
        $value = trim($value, ' ');

        if (is_numeric($value)) {
            if (false === strpos($value, '.')) {
                $value = (int) $value;
            } else {
                $value = (float) $value;
            }
        } elseif ('true' === strtolower($value)) {
            $value = true;
        } elseif ('false' === strtolower($value)) {
            $value = false;
        } elseif ('null' === strtolower($value)) {
            $value = null;
        } elseif (0 === strncmp($value, '"', 1) && '"' === substr($value, -1)) {
            $value = str_replace('\"', '"', substr($value, 1, -1));
        } elseif (0 === strncmp($value, "'", 1) && "'" === substr($value, -1)) {
            $value = str_replace("\\'", "'", substr($value, 1, -1));
        } else {
            throw new \InvalidArgumentException(sprintf('Unknown data type of comparison value "%s".', $value));
        }

        // Evaluate
        switch ($operator) {
            case '==':
                // todo: find a way to stop the CS fixer to change this to ===
                return $tokenValue == $value;

            case '!=':
                // todo: find a way to stop the CS fixer to change this to !==
                return $tokenValue != $value;

            case '===':
                return $tokenValue === $value;

            case '!==':
                return $tokenValue !== $value;

            case '<':
                return $tokenValue < $value;

            case '>':
                return $tokenValue > $value;

            case '<=':
                return $tokenValue <= $value;

            case '>=':
                return $tokenValue >= $value;

            default:
                throw new \InvalidArgumentException(sprintf('Unknown simple token comparison operator "%s".', $operator));
        }
    }
}
