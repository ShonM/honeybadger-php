<?php

namespace Honeybadger;

use Honeybadger\Util\Arr;

class Honeybadger
{
    const VERSION    = '0.0.1';

    const NOTIFIER_NAME = 'honeybadger-php';
    const NOTIFIER_URL  = 'https://github.com/ChessCom/honeybadger-php';
    const LOG_PREFIX    = '** [Honeybadger] ';

    /**
     * @var  Sender  Object responding to `send_to_honeybadger`.
     */
    public $sender;

    /**
     * @var  Config  Honeybadger configuration.
     */
    public $config;

    /**
     * @var  Logger  Honeybadger logger.
     */
    public $logger;

    /**
     * @var  array  Stores custom data for sending user-specific information
     *              in notifications.
     */
    protected $context = array();

    public function __construct(Config $config = null)
    {
        $this->logger = new Logger\Void;
        $this->config = $config ?: new Config;
        $this->sender = new Sender;
    }

    /**
     * Merges supplied `$data` with current context. This can be anything,
     * such as user information.
     *
     * @param  array $data Data to add to the context.
     * @return array The current context.
     */
    public function context(array $data = array())
    {
        return $this->context = array_merge($this->context, $data);
    }

    /**
     * Replaces the context with the supplied data. If no data is provided, the
     * context is emptied.
     *
     * @param  array $data Data to add to the context.
     * @return array The current context.
     */
    public function reset_context(array $data = array())
    {
        return $this->context = $data;
    }

    /**
     * Registers Honeybadger as the global error and exception handler. Any
     * uncaught exceptions and errors will be sent to Honeybadger by default.
     *
     * @return void
     */
    public function handle_errors()
    {
        $error = new Error($this);
        $error->register_handler();

        $exception = new Exception($this);
        $exception->register_handler();
    }

    public function handleError($code, $error, $file = NULL, $line = NULL)
    {
        $handler = new Error($this);
        $handler->handle($code, $error, $file, $line);
    }

    public function handleException($exception)
    {
        $handler = new Exception($this);
        $handler->handle($exception);
    }

    public function report_environment_info()
    {
        $this->logger->add($this->config->log_level, 'Environment info: :info', array(
            ':info' => $this->environment_info(),
        ));
    }

    public function report_response_body($response)
    {
        $this->logger->add($this->config->log_level, "Response from Honeybadger:\n:response", array(
            ':response' => $response,
        ));
    }

    public function environment_info()
    {
        $info = '[PHP: '.phpversion().']';

        if ($this->config->framework) {
            $info .= ' ['.$this->config->framework.']';
        }

        if ($this->config->environment_name) {
            $info .= ' [Env: '.$this->config->environment_name.']';
        }

        return $info;
    }

    /**
     * Sends a notice with the supplied `$exception` and `$options`.
     *
     * @param  Exception $exception The exception.
     * @param  array     $options   Additional options for the notice.
     *
     * @return string    The error identifier.
     */
    public function notify($exception, array $options = array())
    {
        return $this->send_notice($this->build_notice_for($exception, $options));
    }

    /**
     * Sends a notice with the supplied `$exception` and `$options` if it is
     * not an ignored class or filtered.
     *
     * @param  Exception   $exception The exception.
     * @param  array       $options   Additional options for the notice.
     *
     * @return string|null The error identifier. `NULL` if skipped.
     */
    public function notify_or_ignore($exception, array $options = array())
    {
        $notice = $this->build_notice_for($exception, $options);

        if ( ! $notice->is_ignored()) {
            return $this->send_notice($notice);
        }
    }

    public function build_lookup_hash_for($exception, array $options = array())
    {
        $notice = $this->build_notice_for($exception, $options);

        $result = array(
            'action'           => $notice->action,
            'component'        => $notice->component,
            'environment_name' => 'production',
        );

        if ($notice->error_class) {
            $result['error_class'] = $notice->error_class;
        }

        if ($notice->backtrace->has_lines()) {
            $result['file']        = $notice->backtrace->lines[0]->file;
            $result['line_number'] = $notice->backtrace->lines[0]->number;
        }

        return $result;
    }

    private function send_notice($notice)
    {
        if ($this->config->is_public()) {
            return $notice->deliver();
        }
    }

    private function build_notice_for($exception, array $options = array())
    {
        if ($exception instanceof \Exception) {
            $options['exception'] = $this->unwrap_exception($exception);
        } elseif (Arr::is_array($exception)) {
            $options = Arr::merge($options, $exception);
        }

        return new Notice($this, $this->config->merge($options));
    }

    private function unwrap_exception($exception)
    {
        if ($previous = $exception->getPrevious()) {
            return $this->unwrap_exception($previous);
        }

        return $exception;
    }
}
