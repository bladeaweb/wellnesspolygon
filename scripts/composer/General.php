<?php

namespace Utilities\composer;

use Composer\Script\Event;
use SebastianBergmann\GlobalState\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Utilities.
 */
class General {

  public static $debug = FALSE;

  const BIN_PATH_COMPOSER = 'vendor/bin';
  const BIN_PATH_NODE = 'node_modules/.bin';

  /**
   * This allows grunt to run using local install (*nix) or global (windows).
   *
   * @param \Composer\Script\Event $event
   *   Composer script event.
   *
   * @return bool
   *   Command exit status.
   */
  public static function localExecGrunt(Event $event) {
    $command_path = realpath(self::getExecPath('npm', 'node'));
    // Attempt fallback to global npm.
    if ($command_path === FALSE) {
      $command_path = 'grunt';
    }
    $arguments = $event->getArguments();

    return self::localExec($command_path, $arguments);
  }

  /**
   * Composer script wrapper to recursively remove directories/files.
   *
   * @param \Composer\Script\Event $event
   *   Composer script event.
   */
  public static function unlinkRecursiveScript(Event $event) {
    $paths = $event->getArguments();
    $paths = self::unlinkRecursive($paths);
    if (!empty($paths)) {
      $event->getIO()->write(sprintf('Deleted paths: %s', implode(PHP_EOL . '  ', $paths)));
    }
  }

  /**
   * Recursively delete directories and files using Symfony\Filesystem->remove.
   *
   * @param array $paths
   *   Array of paths to delete.
   *
   * @return array
   *   Array of paths that were deleted.
   */
  public static function unlinkRecursive(array $paths) {
    foreach ($paths as &$path) {
      $path = realpath($path);
    }

    // Remove paths that didn't match the filesystem.
    $paths = array_filter($paths, function ($v) {
      return $v !== FALSE;
    });

    if (!empty($paths) && !self::$debug) {
      $fs = new Filesystem();
      $fs->remove($paths);
    }

    return $paths;
  }

  /**
   * Wrapper to run system-wide executable from composer.
   *
   * @param \Composer\Script\Event $event
   *   Composer script event.
   *
   * @return bool
   *   Command result.
   */
  public static function globalExec(Event $event) {
    $arguments = $event->getArguments();

    $command = array_shift($arguments);
    $ignore_error = $command === 'ignore-error' ? TRUE : FALSE;
    $command = $ignore_error ? array_shift($arguments) : $command;
    if ($command === NULL) {
      throw new RuntimeException('globalExec requires at least one argument.');
    }
    $result = self::localExec($command, $arguments);
    if ($ignore_error) {
      return TRUE;
    }

    return $result;
  }

  /**
   * Wrapper for executing a command on the local machine.
   *
   * @param string $command
   *   Command to execute.
   * @param array $arguments
   *   Array of arguments to be passed to the command.
   * @param bool $quiet
   *   If true, command output will be suppressed.
   *
   * @return bool
   *   TRUE on success, FALSE on error.
   */
  public static function localExec($command, array &$arguments = [], $quiet = FALSE) {
    $result = self::localExecRaw($command, $arguments, $quiet);

    return $result['exit_code'] === 0;
  }

  /**
   * Execute a command on the local machine.
   *
   * @param string $command
   *   Command to execute.
   * @param array $arguments
   *   Array of arguments to be passed to the command.
   * @param bool $quiet
   *   If true, command output will be surpressed.
   *
   * @return array
   *   Associative array of exit code and output.
   */
  public static function localExecRaw($command, array &$arguments = [], $quiet = FALSE) {
    $output = [];
    // Generally speaking 0 = no error, non-zero = error.
    $exit_code = 0;

    if (self::$debug) {
      return ['output' => $output, 'exit_code' => $exit_code];
    }
    if (!empty($arguments)) {
      $argument_string = self::combineArguments($arguments);
      $command = sprintf('%s %s', $command, $argument_string);
    }
    exec($command, $output, $exit_code);
    if (!$quiet) {
      print "==> $command";
      foreach ($output as $line) {
        printf('%s%s', $line, PHP_EOL);
      }
    }

    return ['output' => $output, 'exit_code' => $exit_code];
  }

  /**
   * Combine an array of arguments.
   *
   * If indexed: 'value value2', if associative: 'key=value key2=value2'.
   *
   * @param array $arguments
   *   Array of arguments to combine.
   *
   * @return string
   *   Argument string.
   */
  protected static function combineArguments(array &$arguments) {
    if (!self::arrayIsAssoc($arguments)) {
      return implode(' ', $arguments);
    }
    $argument_string = array_reduce(
      array_keys($arguments),
      function ($output, $key) use (&$arguments) {
        return sprintf('%s %s=%s', $output, $key, $arguments[$key]);
      }
    );

    return $argument_string;
  }

  /**
   * Determine if an array is indexed or associative.
   *
   * @param array $array
   *   The array to test.
   *
   * @return bool
   *   TRUE if array is associative, FALSE if indexed.
   */
  protected static function arrayIsAssoc(array &$array) {
    $elements = count($array);
    for ($i = 0; $i < $elements; $i++) {
      if (!isset($array[$i])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the absolute executable binary file path.
   *
   * @param string $bin
   *   The binary file name. E.g. phpcs.
   * @param string $application
   *   The application to which the binary file belongs. E.g. 'node'.
   *
   * @return string
   *   The full executable path or empty string if application isn't defined.
   */
  public static function getExecPath($bin, $application = 'composer') {
    return sprintf('%s%s%s', self::getBinPath($application), DIRECTORY_SEPARATOR, $bin);
  }

  /**
   * Get the absolute bin path for the specified application.
   *
   * @param string $application
   *   Currently 'composer' or 'node'.
   *
   * @return string
   *   The bin directory of the specified application.
   */
  protected static function getBinPath($application = 'composer') {
    // If the constant exists, we've got a defined path for the 'type'.
    $const_name = sprintf('%s%s%s', 'self::', 'BIN_PATH_', strtoupper($application));
    // self::COMPOSER_BIN_PATH.
    if (!defined($const_name)) {
      return '';
    }

    // Resolve to absolute path.
    $bin_path = realpath(constant($const_name));

    if ($bin_path === FALSE) {
      return '';
    }

    return $bin_path;
  }

  /**
   * Clean up docroot directory based on the projects .gitignore file.
   */
  public static function unlinkDocroot() {
    // This calls
    // git ls-files --others --directory -i --exclude-from=.gitignore docroot
    // Essentially listing directories (and files if necessary) excluded
    // from the git repo.
    $git_arguments = [
      'ls-files',
      '--directory',
      '--other',
      '-i',
      '--exclude-from=.gitignore',
      'docroot',
    ];
    $result = self::localExecRaw('git', $git_arguments, TRUE);
    if ($result['exit_code'] === 0) {
      if (!empty($result['output']) && !self::$debug) {
        self::unlinkRecursive($result['output']);
      }
    }
  }

}
