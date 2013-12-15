<?php

namespace Honeybadger;

/**
 * Based on [Kohana's error handler](https://github.com/kohana/core/blob/3.3/master/classes/Kohana/Core.php#L984:L995).
 *
 * @package  Honeybadger
 */
class Error
{
    protected $honeybadger;

    private $previous_handler;

    public function __construct(Honeybadger $honeybadger)
    {
        $this->honeybadger = $honeybadger;
    }

    public function register_handler()
    {
        $this->previous_handler = set_error_handler(array(
            $this, 'handle',
        ));
    }

    public function handle($code, $error, $file = NULL, $line = NULL)
    {
        if (error_reporting() & $code) {
            // This error is not suppressed by current error reporting settings.
            // Convert the error into an ErrorException.
            $exception = new \ErrorException($error, $code, 0, $file, $line);

            // Send the error to Honeybadger.
            $this->honeybadger->notify_or_ignore($exception);
        }

        if (is_callable($this->previous_handler)) {
            // Pass the triggered error on to the previous error handler.
            return call_user_func($this->previous_handler, $code, $error, $file, $line);
        }

        // Execute the PHP error handler.
        return FALSE;
    }

}
