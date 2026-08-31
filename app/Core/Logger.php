<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight PSR-3-style file logger (first-party — no Monolog, ADR-001).
 *
 * Writes daily log files to storage/logs (outside the webroot). Security-relevant
 * events and errors funnel here. Never logs secrets or full request bodies.
 */
final class Logger
{
    private const LEVELS = [
        'debug'     => 0,
        'info'      => 1,
        'notice'    => 2,
        'warning'   => 3,
        'error'     => 4,
        'critical'  => 5,
        'alert'     => 6,
        'emergency' => 7,
    ];

    private int $threshold;

    public function __construct(
        private readonly string $dir,
        private readonly string $file,
        string $minLevel = 'info',
    ) {
        $this->threshold = self::LEVELS[$minLevel] ?? self::LEVELS['info'];
    }

    public function debug(string $m, array $c = []): void     { $this->log('debug', $m, $c); }
    public function info(string $m, array $c = []): void      { $this->log('info', $m, $c); }
    public function notice(string $m, array $c = []): void    { $this->log('notice', $m, $c); }
    public function warning(string $m, array $c = []): void   { $this->log('warning', $m, $c); }
    public function error(string $m, array $c = []): void     { $this->log('error', $m, $c); }
    public function critical(string $m, array $c = []): void  { $this->log('critical', $m, $c); }

    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 1) < $this->threshold) {
            return;
        }

        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate($message, $context),
            $context !== [] ? ' ' . $this->encodeContext($context) : '',
            PHP_EOL,
        );

        @file_put_contents($this->dir . DIRECTORY_SEPARATOR . $this->file, $line, FILE_APPEND | LOCK_EX);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || $val === null) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }

    private function encodeContext(array $context): string
    {
        // Redact anything that looks like a secret before writing.
        $safe = [];
        foreach ($context as $key => $val) {
            $safe[$key] = preg_match('/pass|secret|token|key|authorization/i', (string) $key)
                ? '***REDACTED***'
                : $val;
        }

        return (string) json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
