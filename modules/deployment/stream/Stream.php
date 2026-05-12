<?php

if (!defined("BASE_URL")) {
  exit("No direct script access allowed");
}

class Stream extends Trongate
{
  function start(): void
  {
    header("Content-Type: text/event-stream");
    header("Cache-Control: no-cache");
    header("X-Accel-Buffering: no");
    header("Connection: keep-alive");
  }

  function prepare_long_running(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }

    ignore_user_abort(true);
    ini_set("output_buffering", "Off");
    ini_set("zlib.output_compression", "Off");
    set_time_limit(0);
    while (ob_get_level() > 0) {
      ob_end_clean();
    }
  }

  function emit(string $line, string $event = ""): void
  {
    if ($event !== "") {
      echo "event: {$event}\n";
    }
    echo "data: " . $line . "\n\n";
    flush();
  }

  function done(array $payload): void
  {
    $this->emit(json_encode($payload), "done");
  }

  function ping(string $comment = "ping"): void
  {
    echo ": {$comment}\n\n";
    flush();
  }
}
