<?php

namespace Utilities\composer;

/**
 * Class CodeStyle.
 */
class CodeStyle {

  /**
   * Run phpcs linting.
   */
  public static function cs() {
    $phpcs_status = self::lint(
      sprintf(General::getExecPath('phpcs', 'composer')),
      self::getCodeSnifferPath(),
      General::getExecPath('eslint', 'node'),
      '--colors -s -p'
    );
    // Also lint scss.
    $shell_command = sprintf('%s %s',
      General::getExecPath('sass-lint', 'node'),
      // This suppresses error backtrace and formats the output nicely.
      '-qv --max-warnings 0'
    );

    $sass_status = General::localExec($shell_command);

    return $phpcs_status && $sass_status;
  }

  /**
   * Run phpcbf tool.
   */
  public static function cbf() {
    self::lint(
      General::getExecPath('phpcbf'),
      self::getCodeSnifferPath(),
      General::getExecPath('eslint', 'node'),
      '--colors -s -p'
    );
  }

  /**
   * Get the code sniffer path.
   *
   * @return string
   *   Path to code sniffer.
   */
  protected static function getCodeSnifferPath() {
    return implode(DIRECTORY_SEPARATOR, [
      getcwd(),
      'vendor',
      'drupal',
      'coder',
      'coder_sniffer',
    ]);
  }

  /**
   * Run linting with specified tool and output to client.
   *
   * @param string $bin
   *   The linting tool binary path.
   * @param string $coder_sniffer_path
   *   The path to coder_sniffer.
   * @param string $eslint_path
   *   The path to eslint.
   * @param string $linting_parameters
   *   Extra command parameters.
   *
   * @return bool
   *   Command exit code.
   */
  protected static function lint($bin, $coder_sniffer_path, $eslint_path, $linting_parameters) {
    $shell_command = sprintf(
      '%s --runtime-set installed_paths %s --runtime-set eslint_path %s %s',
      $bin,
      $coder_sniffer_path,
      $eslint_path,
      $linting_parameters
    );
    return General::localExec($shell_command);
  }

}
