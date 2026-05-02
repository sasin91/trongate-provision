<?php
class Storage extends Trongate {

    private string $root;

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        $this->root = __DIR__;
    }

    /** Absolute path to a file/dir within this module's storage root. */
    public function path(string $relative): string {
        return $this->root . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
    }

    /**
     * Write data to a path, creating the directory and .htaccess deny file if needed.
     * Returns the absolute path on success, false on failure.
     */
    public function put(string $relative, string $data): string|false {
        $full = $this->path($relative);
        $dir  = dirname($full);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }
        $deny = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($deny)) {
            @file_put_contents($deny, "Require all denied\nDeny from all\n");
        }
        return file_put_contents($full, $data) !== false ? $full : false;
    }

    /** Ensure a subdirectory exists (with .htaccess deny). Returns the absolute path or false. */
    public function ensure_dir(string $relative): string|false {
        $dir = $this->path($relative);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }
        $deny = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($deny)) {
            @file_put_contents($deny, "Require all denied\nDeny from all\n");
        }
        return $dir;
    }

    public function exists(string $relative): bool {
        return file_exists($this->path($relative));
    }

    /** @return string[] Absolute paths matching the glob pattern. */
    public function glob(string $pattern): array {
        return glob($this->path($pattern)) ?: [];
    }

    public function delete(string $relative): bool {
        $full = $this->path($relative);
        return file_exists($full) && @unlink($full);
    }

    public function mtime(string $relative): int|false {
        return filemtime($this->path($relative));
    }
}
