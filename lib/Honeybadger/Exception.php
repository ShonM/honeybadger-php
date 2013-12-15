<?php

namespace Honeybadger;

/**
 * Based on [Kohana's exception handler](https://github.com/kohana/core/blob/3.4/develop/classes/Kohana/Kohana/Exception.php#L102:L130).
 *
 * @package  Honeybadger
 */
class Exception
{
    protected $honeybadger;

    private $previous_handler;

    public function __construct(Honeybadger $honeybadger)
    {
        $this->honeybadger = $honeybadger;
    }

    public function register_handler()
    {
        $this->previous_handler = set_exception_handler(array(
            $this, 'handle',
        ));
    }

    public function handle(\Exception $e)
    {
        try {
            // Attempt to send this exception to Honeybadger.
            $this->honeybadger->notify_or_ignore($e);
        } catch (\Exception $e) {
            if (is_callable($this->previous_handler)) {
                return call_user_func($this->previous_handler, $e);
            } else {
                // Clean the output buffer if one exists.
                ob_get_level() AND ob_clean();

                // Set the Status code to 500, and Content-Type to text/plain.
                header('Content-Type: text/plain; charset=utf-8', TRUE, 500);

                echo 'Someting went terribly wrong.';

                // Exit with a non-zero status.
                exit(1);
            }
        }

        if (is_callable($this->previous_handler)) {
            return call_user_func($this->previous_handler, $e);
        }
    }

}
