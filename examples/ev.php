<?php


class EvHandler implements http\Client\Curl\User
{
	private $client;
	private $run;
	private $ios = [];
	private $timeout;

	function __construct(http\Client $client) {
		$this->client = $client;
	}

	function init($run) {
		$this->run = $run;
	}

	function timer(int $timeout_ms) {
		if (isset($this->timeout)) {
			$this->timeout->set($timeout_ms/1000, 0);
			$this->timeout->start();
		} else {
			$this->timeout = new EvTimer($timeout_ms/1000, 0, function() {
				if (!call_user_func($this->run, $this->client)) {
					if ($this->timeout) {
						$this->timeout->stop();
						$this->timeout = null;
					}
				}
			});
		}
	}

	function socket($socket, int $action) {
		switch ($action) {
			case self::POLL_NONE:
				break;
			case self::POLL_REMOVE:
				echo "U";
				if (isset($this->ios[(int) $socket])) {
					$this->ios[(int) $socket]->stop();
					unset($this->ios[(int) $socket]);
				}
				break;
			default:
				$ev = 0;
				if ($action & self::POLL_IN) {
					$ev |= Ev::READ;
				}
				if ($action & self::POLL_OUT) {
					$ev |= Ev::WRITE;
				}
				if (isset($this->ios[(int) $socket])) {
					$this->ios[(int) $socket]->set($socket, $ev);
				} else {
					$this->ios[(int) $socket] = new EvIo($socket, $ev, function($watcher, $events) use($socket) {
						$action = 0;
						if ($events & Ev::READ) {
							$action |= self::POLL_IN;
						}
						if ($events & Ev::WRITE) {
							$action |= self::POLL_OUT;
						}
						if (!call_user_func($this->run, $this->client, $socket, $action)) {
							if ($this->timeout) {
								$this->timeout->stop();
								$this->timeout = null;
							}
						}
					});
				}
				break;
		}
	}

	function once() {
		echo "O";
		Ev::run(EV::RUN_NOWAIT);
	}
	function wait(int $timeout_ms = null) {
		echo "W";
		Ev::run(EV::RUN_ONCE);
	}
	function send() {
		echo "!";
		Ev::verify();
		Ev::run();
	}
}
