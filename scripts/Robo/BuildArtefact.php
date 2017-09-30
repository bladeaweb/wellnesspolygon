<?php

use Robo\Exception\TaskException;
use Robo\ResultData;
use Robo\Tasks;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class BuildArtefact extends Tasks {

  /**
   * Defines remote name for git.
   */
  const GIT_REMOTE_NAME = 'origin';

  /**
   * Defines a prefix for artefact's commit message.
   *
   * This is used to identify a string that contains previous commit to track
   * commit difference.
   */
  const COMMIT_DESCRIPTION_PREFIX = 'Source commit: ';

  /**
   * Defines ascending sorting order of commits in the list.
   */
  const GIT_COMMITS_SORT_ASC = 'asc';

  /**
   * Defines descending sorting order of commits in the list.
   */
  const GIT_COMMITS_SORT_DESC = 'desc';

  /**
   * Defines default file name for artefact map file.
   */
  const MAP_FILE_NAME = '.artefactmap';

  /**
   * Defines a prefix for negating items in map file.
   */
  const MAP_NEGATE_PREFIX = '!';

  /**
   * Path to the root directory.
   *
   * @var string
   */
  protected $rootDir;

  /**
   * Path to source directory.
   *
   * @var string
   */
  protected $srcDir;

  /**
   * Path to artefact directory.
   *
   * @var string
   */
  protected $artefactDir;

  /**
   * Path to the map file.
   *
   * @var string
   */
  protected $mapFile;

  /**
   * Git remote file path or URI.
   *
   * @var string
   */
  protected $gitRemote;

  /**
   * Git remote branch.
   *
   * @var string
   */
  protected $gitRemoteBranch;

  /**
   * Git default remote branch.
   *
   * @var string
   */
  protected $gitRemoteBranchDefault;

  /**
   * Branch name to apply tags to.
   *
   * @var string
   */
  protected $gitTagBranchFilter;

  /**
   * Flag to know whether deployment is required.
   *
   * @var bool
   */
  protected $needsDeployment;

  /**
   * Flag to know if cleanup is required.
   *
   * @var bool
   */
  protected $needsCleanup;

  /**
   * Stack of original current working directories.
   *
   * This is used throughout commands to track working directories.
   * Usually, each command would call setCwd() in the beginning and restoreCwd()
   * at the end of the run.
   *
   * @var array
   */
  protected $originalCwdStack = [];

  /**
   * File system for custom commands.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * BuildArtefact constructor.
   *
   * @param object $fs
   *   Optional file system object. New object created if not provided.
   */
  public function __construct($fs = NULL) {
    $this->fs = $fs ? $fs : new Filesystem();
  }

  /**
   * Build deployment artefact.
   *
   * @param string $remote
   *   Path to the remote git repository.
   * @param string $branch
   *   Branch name of the remote git repository.
   * @param array $opts
   *   Options.
   *
   * @option $root Path to the root for file path resolution. If not specified,
   *   current directory is used.
   * @option $src Directory where source repository is located. If not
   *   specified, root directory is used.
   * @option $map Path to the files mapping file. If not specified, all contents
   *   of the source directory will be copied to the artefact directory.
   * @option $artefact Location of the directory where artefact will be created.
   * @option $git-remote-branch-default Default remote branch for the case if
   *   specified remote branch does not exist. Defaults to 'master'.
   * @option $git-tag-branch-filter If specified, tags will be applied only if
   *   $branch matches specified branch. This allows to prevent, for example,
   *   tags being added to the `develop` branch instead of `master`.
   * @option $deploy Deploy built artefact to the remote repository. Defaults to
   *   FALSE.
   * @option $cleanup Cleanup artefact directory after the build.
   */
  public function artefact($remote, $branch, array $opts = [
    'root' => InputOption::VALUE_REQUIRED,
    'src' => InputOption::VALUE_REQUIRED,
    'map' => NULL,
    'artefact' => 'artefact',
    'git-remote-branch-default' => 'master',
    'git-tag-branch-filter' => InputOption::VALUE_REQUIRED,
    'deploy' => FALSE,
    'cleanup' => FALSE,
  ]) {
    $this->resolveOptions($opts);
    $this->setGitRemote($remote);
    $this->setGitRemoteBranch($branch);
    $this->checkRequirements();

    $this->showInfo();

    try {
      $this->doBuild();
    }
    finally {
      if ($this->needsCleanup) {
        // Remove all files from destination directory.
        $this->taskDeleteDir($this->artefactDir)->run();
        $this->sayOkay(sprintf("Removed artefact directory '%s'", $this->artefactDir));
      }
    }
  }

  /**
   * @defgroup artefact build methods Artefact build related functionality
   * @{
   */

  /**
   * Perform actual artefact build.
   */
  protected function doBuild() {
    $this->buildArtefactPrepare();

    $artefact_has_new_changes = $this->buildArtefactHasChanged();
    $new_tags = [];
    // Enable below when tag handling is fixed.
    // @code
    // $new_tags = $this->buildArtefactFindNewTags();
    // @endocode
    if (!$artefact_has_new_changes && empty($new_tags)) {
      $this->sayOkay('No changes detected in the result artefact. Deployment will not proceed.');

      return;
    }

    if ($artefact_has_new_changes) {
      $this->buildArtefactCommitChanges();
    }

    if (!empty($new_tags)) {
      $this->buildArtefactAddTags($new_tags);
    }

    $this->buildArtefactPush();
  }

  /**
   * Build step: prepare artefact.
   */
  protected function buildArtefactPrepare() {
    $this->prepareDir($this->artefactDir);

    $this->gitInit($this->artefactDir);
    $this->gitAddRemote($this->artefactDir, $this->gitRemote);

    $existing_remote_branch = $this->gitFindExistingBranch($this->gitRemote, [$this->gitRemoteBranch, $this->gitRemoteBranchDefault]);
    $this->gitPull($this->artefactDir, $existing_remote_branch);
    // Branch does not exist on remote - create it locally.
    if ($existing_remote_branch != $this->gitRemoteBranch) {
      $this->gitCreateBranch($this->artefactDir, $this->gitRemoteBranch);
    }
    $this->say("Removing all but '.git' files from artefact directory");
    $this->cleanDir($this->artefactDir, ['.git']);

    // Copy files from source directory to artefact directory.
    $this->say('Copying files from source to artefact directory');
    $this->copyFilesFromMap($this->srcDir, $this->artefactDir, $this->mapFile, ['.git']);
  }

  /**
   * Build step: check if artefact has any file changes.
   */
  protected function buildArtefactHasChanged() {
    return $this->gitRepoHasChanged($this->artefactDir);
  }

  /**
   * Build step: find new artefact tags.
   */
  protected function buildArtefactFindNewTags() {
    // Resolve tags using information from the last commit.
    $latest_dst_stored_commit = $this->retrieveLatestDstCommit($this->srcDir, $this->artefactDir);
    // Get source tags, but only since last commit. This prevents any tags
    // set for commits that have already been added to artefact during previous
    // deployment to be accounted for as new commits.
    $src_tags = $this->gitGetTagsSinceCommit($this->srcDir, $latest_dst_stored_commit['hash']);
    $artefact_tags = $this->gitGetAllTags($this->artefactDir);
    $dst_top_tags = $this->gitGetTagsFromLastCommit($this->artefactDir);
    $src_tags = array_diff_key($src_tags, $dst_top_tags);

    $invalid_tags = array_keys(array_intersect_key($src_tags, $artefact_tags));
    if (!empty($invalid_tags)) {
      throw new Exception(sprintf("Source tag(s) '%s' already exists in remote repository. Deployment will not proceed.", implode(', ', $invalid_tags)));
    }

    // Use only unique non-existing tags.
    $tags = array_keys(array_diff_key($src_tags, $artefact_tags));

    return $tags;
  }

  /**
   * Build step: commit artefact changes.
   */
  protected function buildArtefactCommitChanges() {
    $commit_message = $this->prepareArtefactCommitMessage($this->srcDir, $this->artefactDir);
    if ($commit_message) {
      $this->gitCommit($this->artefactDir, $commit_message);
    }
  }

  /**
   * Build step: add provided tags to the artefact.
   */
  protected function buildArtefactAddTags($tags) {
    if (is_null($this->gitTagBranchFilter) || $this->gitTagBranchFilter == $this->gitRemoteBranch) {
      $this->gitAddTags($this->artefactDir, $tags);
    }
    else {
      $this->sayOkay(sprintf("Skip adding tag(s) '%s' as current branch '%s' did not meet tag filter '%s'", implode(', ', $tags), $this->gitRemoteBranch, $this->gitTagBranchFilter));
    }
  }

  /**
   * Build step: push artefact to the remote.
   */
  protected function buildArtefactPush() {
    $this->gitPush($this->artefactDir, $this->gitRemoteBranch, self::GIT_REMOTE_NAME, !$this->needsDeployment);
  }

  /**
   * @} "Artefact build related functionality"
   */

  /**
   * @defgroup artefact creation methods Artefact creation related functionality
   * @{
   */

  /**
   * Resolve and validate CLI options values into internal values.
   *
   * @param array $options
   *   Array of CLI options.
   */
  protected function resolveOptions(array $options) {
    $this->rootDir = !empty($options['root']) ? $this->getAbsolutePath($options['root']) : $this->getRootDir();
    $this->pathsExist($this->rootDir);

    // Default source to the root directory.
    $this->srcDir = !empty($options['src']) ? $this->getAbsolutePath($options['src']) : $this->getRootDir();
    $this->pathsExist($this->srcDir);

    if (!empty($options['map'])) {
      $this->mapFile = $this->getAbsolutePath($options['map']);
      $this->pathsExist($this->mapFile);
    }

    $this->artefactDir = !empty($options['artefact']) ? $this->getAbsolutePath($options['artefact']) : NULL;

    $this->gitRemoteBranchDefault = isset($options['git-remote-branch-default']) ? $options['git-remote-branch-default'] : NULL;
    $this->gitTagBranchFilter = isset($options['git-tag-branch-filter']) ? $options['git-tag-branch-filter'] : NULL;

    $this->needsDeployment = !empty($options['deploy']);
    $this->needsCleanup = !empty($options['cleanup']);
  }

  /**
   * Show artefact build information.
   */
  protected function showInfo() {
    $this->writeln('----------------------------------------------------------------------');
    $this->writeln(' Artefact information');
    $this->writeln('----------------------------------------------------------------------');
    $this->writeln(' Root directory:        ' . $this->rootDir);
    $this->writeln(' Source directory:      ' . $this->srcDir);
    $this->writeln(' Artefact directory:    ' . $this->artefactDir);
    $this->writeln(' Remote repository:     ' . $this->gitRemote);
    $this->writeln(' Remote branch:         ' . $this->gitRemoteBranch);
    $this->writeln(' Remote default branch: ' . $this->gitRemoteBranchDefault);
    $this->writeln(' Map file:              ' . ($this->mapFile ? $this->mapFile : 'No'));
    $this->writeln(' Will deploy:           ' . ($this->needsDeployment ? 'Yes' : 'No'));
    $this->writeln(' Will cleanup:          ' . ($this->needsCleanup ? 'Yes' : 'No'));
    $this->writeln('----------------------------------------------------------------------');
  }

  /**
   * Check that there all requirements are met in order to to run this command.
   */
  protected function checkRequirements() {
    // @todo: Refactor this into more generic implementation.
    $this->say('Checking requirements');
    if (!$this->commandAvailable('git')) {
      throw new \RuntimeException('At least one of the script running requirements was not met');
    }
    $this->sayOkay('All requirements were met');
  }

  /**
   * Check if provided location is local path or remote URI.
   *
   * @param string $location
   *   Local path or remote URI.
   * @param string $type
   *   One of the predefined types:
   *   - any: Expected to have either local path or remote URI provided.
   *   - local: Expected to have local path provided.
   *   - uri: Expected to have remote URI provided.
   *
   * @return bool
   *   Returns TRUE if location matches type, FALSE otherwise.
   */
  protected function isGitRemote($location, $type = 'any') {
    $is_local = $this->pathsExist($this->getAbsolutePath($location), FALSE);
    $is_uri = (bool) preg_match('/^(?:git|ssh|https?|[\d\w\.\-_]+@[\w\.\-]+):(?:\/\/)?[\w\.@:\/~_-]+\.git(?:\/?|\#[\d\w\.\-_]+?)$/', $location);

    switch ($type) {
      case 'any':
        return $is_local || $is_uri;

      case 'local':
        return $is_local;

      case 'uri':
        return $is_uri;

      default:
        throw new InvalidArgumentException(sprintf('Invalid argument "%s" provided', $type));
    }
  }

  /**
   * Set git remote location.
   *
   * @param string $location
   *   Path or URL of the remote git repository.
   */
  protected function setGitRemote($location) {
    if (!$this->isGitRemote($location)) {
      throw new RuntimeException(sprintf('Incorrect value "%s" specified for git remote', $location));
    }
    $this->gitRemote = $this->isGitRemote($location, 'local') ? $this->getAbsolutePath($location) : $location;
  }

  /**
   * Set the branch of the remote repository.
   *
   * @param string $branch
   *   Branch of the remote repository.
   */
  protected function setGitRemoteBranch($branch) {
    if (!preg_match('/^(?!\/|.*(?:[\/\.]\.|\/\/|\\|@\{))[^\040\177\s\~\^\:\?\*\[]+(?<!\.lock)(?<![\/\.])$/', $branch)) {
      throw new RuntimeException(sprintf('Incorrect value "%s" specified for git remote branch', $branch));
    }
    $this->gitRemoteBranch = $branch;
  }

  /**
   * Retrieve latest commit in one of artefact's previous commits' messages.
   *
   * @param string $src
   *   Path to source repo.
   * @param string $dst
   *   Path to artefact repo.
   *
   * @return array
   *   Resolved commit.
   */
  protected function retrieveLatestDstCommit($src, $dst) {
    // Find latest commit in one of previous commits' messages using special
    // string like 'Source commit: <hash>'.
    $stored_commit_hash = $this->extractLastSrcCommitHashFromDst($dst);
    // If there is no latest commit in artefact repository, use the very
    // first commit in the source repository.
    $commit = $stored_commit_hash ? $this->gitGetCommits($src, [
      'from' => $stored_commit_hash,
      'limit' => 1,
      // Get the very first commit in the list of commits.
      'order' => self::GIT_COMMITS_SORT_ASC,
    ])[0] : $this->gitGetFirstCommit($src);
    $this->sayOkay(sprintf("Using hash '%s' as latest artefact commit", $commit['hash']));

    return $commit;
  }

  /**
   * Returns the latest source's commit hash in artefact repository.
   *
   * @param string $dst
   *   Destination repository directory.
   *
   * @return string|null
   *   The hash of the last commit in artefact repository if latest commit
   *   message was found in the commit's message description, NULL otherwise.
   */
  protected function extractLastSrcCommitHashFromDst($dst) {
    $latest_commit = $this->gitGetLastCommit($dst, ['message' => self::COMMIT_DESCRIPTION_PREFIX]);

    preg_match('/' . self::COMMIT_DESCRIPTION_PREFIX . '([0-9a-f]+)/', $latest_commit['description'], $matches);

    return isset($matches[1]) ? $matches[1] : NULL;
  }

  /**
   * Prepare artefact commit message.
   */
  protected function prepareArtefactCommitMessage($srcDir, $artefactDir) {
    $latest_dst_stored_commit = $this->retrieveLatestDstCommit($srcDir, $artefactDir);
    // Get a list of commits since last commit is created and commit all
    // changes.
    $commits_list = $this->gitGetCommitsSince($srcDir, $latest_dst_stored_commit);
    // Remove the commit itself.
    array_shift($commits_list);

    if (empty($commits_list)) {
      return FALSE;
    }

    // Special case when last commit is the very first commit, meaning that
    // the very first artefact commit. In this case, we are adding the very
    // first commit to the list of commits.
    $commits_list = $this->gitGetFirstCommit($srcDir)['hash'] == $latest_dst_stored_commit['hash'] ? array_merge([$latest_dst_stored_commit], $commits_list) : $commits_list;
    $commit_message = $this->createDstCommitMessage($this->gitGetLastCommit($srcDir), $commits_list);

    return $commit_message;
  }

  /**
   * Create commit message for artefact repository.
   *
   * @param array $last_commit
   *   Latest source commit.
   * @param array $commits
   *   Array of commits to use information about in commit message.
   *
   * @return string
   *   Commit message with subject and description combined into a single
   *   string.
   */
  protected function createDstCommitMessage(array $last_commit, array $commits) {
    $message = 'Deployment commit on ' . $this->date('Y/m/d h:m:s');
    if (!empty($last_commit['hash'])) {
      // Subject and description should be separated by 2 new lines.
      $message .= PHP_EOL . PHP_EOL;
      $message .= self::COMMIT_DESCRIPTION_PREFIX . $last_commit['hash'] . PHP_EOL;
      if (!empty($commits)) {
        $message .= PHP_EOL;
        $message .= 'Commits since last artefact build:' . PHP_EOL;
        $message .= PHP_EOL;
        $message .= implode(PHP_EOL, array_map(function ($commit) {
          return sprintf('%s (%s)', $commit['subject'], substr($commit['hash'], 0, 8));
        }, $commits));
      }
    }

    return $message;
  }

  /**
   * Copy files from source to destination using optional map file.
   *
   * @param string $src
   *   Source path.
   * @param string $dst
   *   Destination path.
   * @param string|null $map
   *   Path to the map file. If not provided - all files from $src path are
   *   used.
   * @param array $exclude
   *   Array of paths to exclude.
   */
  protected function copyFilesFromMap($src, $dst, $map = NULL, array $exclude = []) {
    if ($map) {
      // Get all paths from map file.
      $paths = $this->extractKeyValuesFromFile($map);

      // Get all excludes from parsed paths.
      foreach ($paths as $path_src => $path_dst) {
        if (substr($path_src, 0, strlen(self::MAP_NEGATE_PREFIX)) == self::MAP_NEGATE_PREFIX) {
          $exclude[] = substr($path_src, strlen(self::MAP_NEGATE_PREFIX));
          unset($paths[$path_src]);
        }
      }

      foreach ($paths as $path_src => $path_dst) {
        $path_src_absolute = $this->getAbsolutePath($src . DIRECTORY_SEPARATOR . $path_src);
        $path_dst_absolute = $this->getAbsolutePath($dst . DIRECTORY_SEPARATOR . $path_dst);
        $this->pathsExist($path_src_absolute);

        // Dir to dir.
        if (is_dir($path_src_absolute)) {
          $this->copyDir($path_src_absolute, $path_dst_absolute, $exclude);
        }
        // File to dir or file.
        elseif (!in_array($path_src_absolute, $exclude)) {
          // Destination path could be exact file location or a directory
          // to copy to.
          // Check that if it is a directory, create it and update the file
          // name.
          // The only way to say that something is intended to be a directory
          // without actual directory on the disk, is to check for the directory
          // separator.
          if (substr($path_dst, -1) == DIRECTORY_SEPARATOR) {
            $this->taskFilesystemStack()
              ->stopOnFail()
              ->mkdir($path_dst_absolute);

            $path_dst_absolute = $path_dst_absolute . DIRECTORY_SEPARATOR . basename($path_src_absolute);
          }

          $res = $this->taskFilesystemStack()
            ->stopOnFail()
            ->copy($path_src_absolute, $path_dst_absolute)
            ->run();

          if (!$res->wasSuccessful()) {
            throw new Exception(sprintf('Unable to copy mapped file "%s" to "%s"', $path_src_absolute, $path_dst_absolute));
          }
        }
      }
    }
    else {
      $this->copyDir($this->getAbsolutePath($src), $this->getAbsolutePath($dst), $exclude);
    }
  }

  /**
   * Extract key/values from provided file.
   *
   * @param string $file
   *   File path.
   * @param string $delimiter_key_value
   *   Delimiter between key and value.
   * @param string $delimiter_comments
   *   Delimiter used for comments. Lines starting with this delimiter are not
   *   parsed.
   *
   * @return array
   *   Array of key/value pares, extracted from $file using $delimiter for
   *   key/value separation.
   */
  protected function extractKeyValuesFromFile($file, $delimiter_key_value = ':', $delimiter_comments = '#') {
    $paths = [];

    $contents = file_get_contents($file);
    $lines = preg_split('/\R/', $contents);
    foreach ($lines as $line) {
      // Skip empty lines and comments.
      if (empty($line) || strpos(trim($line), $delimiter_comments) === 0) {
        continue;
      }
      $src_item = $line;
      $dst_item = '';
      if (strpos($line, $delimiter_key_value) !== FALSE) {
        list($src_item, $dst_item) = explode($delimiter_key_value, $line, 2);
      }
      $paths[$src_item] = $dst_item;
    }

    return $paths;
  }

  /**
   * @} "Artefact creation related functionality"
   */

  /**
   * @defgroup filesystem methods Filesystem-related functionality
   * @{
   */

  /**
   * Copy files from source to destination directory, excluding files.
   *
   * @param string $src
   *   Source directory.
   * @param string $dst
   *   Destination directory.
   * @param array $exclude
   *   Array of excluded paths to skip during the cleanup. Paths are relative
   *   to the specified source directory.
   */
  protected function copyDir($src, $dst, array $exclude = []) {
    try {
      $this->doCopyDir($src, $dst, $exclude);
      $res = new ResultData();
    }
    catch (Exception $exception) {
      $res = new ResultData(ResultData::EXITCODE_ERROR);
    }
    // Using our own copyDir() until Robo supports respecting of symlinks.
    // @see https://github.com/consolidation/Robo/issues/591
    // @code
    // $res = $this->taskCopyDir([$src => $dst])
    //  ->exclude($exclude)
    //  ->run();
    // @endcode
    if ($res->wasSuccessful()) {
      $this->sayOkay(sprintf("Directory '%s' was copied to '%s'", $src, $dst));
    }
  }

  /**
   * Copies a directory to another location.
   *
   * @param string $src
   *   Source directory.
   * @param string $dst
   *   Destination directory.
   * @param array $exclude
   *   Array of exclusion paths.
   * @param string $parent
   *   Parent directory.
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function doCopyDir($src, $dst, array $exclude = [], $parent = '') {
    $dir = @opendir($src);
    if (FALSE === $dir) {
      throw new TaskException($this, sprintf('Cannot open source directory "%s"', $src));
    }
    if (!is_dir($dst)) {
      mkdir($dst, 0755, TRUE);
    }
    while (FALSE !== ($file = readdir($dir))) {
      // Support basename and full path exclusion.
      if (in_array($file, $exclude) || in_array($parent . $file, $exclude) || in_array($src . DIRECTORY_SEPARATOR . $file, $exclude)) {
        continue;
      }
      if (($file !== '.') && ($file !== '..')) {
        $srcFile = $src . '/' . $file;
        $destFile = $dst . '/' . $file;
        if (is_dir($srcFile)) {
          $this->doCopyDir($srcFile, $destFile, $exclude, $parent . $file . DIRECTORY_SEPARATOR);
        }
        elseif (is_link($srcFile)) {
          $this->fs->symlink(readlink($srcFile), $destFile);
        }
        else {
          $this->fs->copy($srcFile, $destFile, TRUE);
        }
      }
    }
    closedir($dir);
  }

  /**
   * Remove all files from the directory, except for the excluded.
   *
   * @param string $dir
   *   Directory to clear files from.
   * @param array $exclude
   *   Array of excluded paths to skip during the cleanup. Paths are relative
   *   to the specified directory.
   */
  protected function cleanDir($dir, array $exclude = []) {
    $files = $this->findFiles($dir, $exclude);
    $this->fs->remove($files);
  }

  /**
   * Prepare a directory and cleanup it contents, if required.
   *
   * @param string $dir
   *   Directory path to prepare.
   * @param bool $cleanup
   *   Boolean flag whether to remove the contents of the directory. Defaults
   *   to TRUE.
   */
  protected function prepareDir($dir, $cleanup = TRUE) {
    $ret = $this->taskFilesystemStack()
      ->mkdir($dir)
      ->run();

    if ($ret->wasSuccessful()) {
      $this->sayOkay(sprintf("Created directory '%s'", $dir));
    }

    if ($cleanup) {
      $this->cleanDir($dir);
      $this->sayOkay(sprintf("Removed contents of directory '%s'", $dir));
    }
  }

  /**
   * Find all files in specified directory, excluding files from the list.
   *
   * @param string $dir
   *   Directory to search for files in.
   * @param array $exclude
   *   Array of excluded paths to skip during the cleanup. Paths are relative
   *   to the specified directory.
   * @param bool $includeDirs
   *   Include directories into found files list. Defaults to TRUE.
   *
   * @return array
   *   Array of relative found file paths.
   */
  protected function findFiles($dir, array $exclude = [], $includeDirs = TRUE) {
    $finder = new Finder();

    $files = $finder->files()
      ->in($dir)
      ->ignoreVCS(FALSE)
      ->ignoreDotFiles(FALSE)
      ->exclude($exclude);

    $paths = [];
    foreach ($files as $file) {
      $paths[] = $file->getRealPath();
    }

    if ($includeDirs) {
      $directories = $finder->directories()
        ->in($dir)
        ->ignoreVCS(FALSE)
        ->ignoreDotFiles(FALSE)
        ->exclude($exclude);
      foreach ($directories as $directory) {
        $paths[] = $directory->getRealPath();
      }
    }

    return $paths;
  }

  /**
   * Check that path exists.
   *
   * @param string|array $paths
   *   File name or array of file names to check.
   * @param bool $strict
   *   If TRUE and the file does not exist, an exception will be thrown.
   *   Defaults to TRUE.
   *
   * @return bool
   *   TRUE if file exists and FALSE if not, but only if $strict is FALSE.
   *
   * @throws \Exception
   *   If at least one file does not exist.
   */
  protected function pathsExist($paths, $strict = TRUE) {
    $paths = is_array($paths) ? $paths : [$paths];
    if (!$this->fs->exists($paths)) {
      if ($strict) {
        throw new Exception(sprintf('One of the files or directories does not exist: %s', implode(', ', $paths)));
      }
      else {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Check that a command is available in current session.
   *
   * @param string $command
   *   Command to check.
   *
   * @return bool
   *   TRUE if command is available, FALSE otherwise.
   */
  protected function commandAvailable($command) {
    // @todo: Find a better way to do this.
    $result = $this->taskExecStack()
      ->exec('which ' . $command)
      ->run();

    return $result->wasSuccessful();
  }

  /**
   * Set current working directory.
   *
   * It is important to note that this should be called in pair with
   * cwdRestore().
   */
  protected function cwdSet($dir) {
    chdir($dir);
    $this->originalCwdStack[] = $dir;
  }

  /**
   * Set current working directory to a previously saved path.
   *
   * It is important to note that this should be called in pair with cwdSet().
   */
  protected function cwdRestore() {
    $dir = array_shift($this->originalCwdStack);
    if ($dir) {
      chdir($dir);
    }
  }

  /**
   * Get current working directory.
   *
   * @return string
   *   Full path of current working directory.
   */
  protected function cwdGet() {
    return getcwd();
  }

  /**
   * Get absolute path for provided file.
   *
   * @param string $file
   *   File to resolve. If absolute, no resolution will be performed.
   * @param string $root
   *   Optional path to root dir. If not provided, internal root path is used.
   *
   * @return string
   *   Absolute path for provided file.
   */
  protected function getAbsolutePath($file, $root = NULL) {
    if ($this->fs->isAbsolutePath($file)) {
      return $this->realpath($file);
    }
    $root = $root ? $root : $this->getRootDir();
    $root = $this->realpath($root);
    $file = $root . DIRECTORY_SEPARATOR . $file;
    $file = $this->realpath($file);

    return $file;
  }

  /**
   * Get root directory.
   *
   * @return string
   *   Currently set value of the root directory, the directory where the script
   *   was started from or current working directory.
   */
  protected function getRootDir() {
    if ($this->rootDir) {
      return $this->rootDir;
    }
    elseif (isset($_SERVER['PWD'])) {
      return $_SERVER['PWD'];
    }

    return getcwd();
  }

  /**
   * Set root directory.
   *
   * @param string $path
   *   Path to set as a root dir.
   */
  protected function setRootDir($path) {
    $path = realpath($path);
    $this->pathsExist($path);
    $this->rootDir = $path;
  }

  /**
   * @} "Filesystem-related functionality"
   */

  /**
   * @defgroup git methods Git-related functionality
   * @{
   */

  /**
   * Get unified git command.
   *
   * @param string $location
   *   Optional repository location.
   *
   * @return Robo\Task\Base\Exec
   *   Exect task.
   */
  protected function gitCommand($location = NULL) {
    $git = $this->taskExec('git');
    $git->arg('--no-pager');
    if (!empty($location)) {
      $git->arg('--git-dir=' . $location . '/.git');
      $git->arg('--work-tree=' . $location);
    }

    return $git;
  }

  /**
   * Initialise empty git repository in the directory.
   *
   * @param string $dir
   *   Repository directory name.
   */
  protected function gitInit($dir) {
    $this->cwdSet($dir);

    $res = $this
      ->taskExec('git')
      ->arg('init')
      ->run();

    if ($res->wasSuccessful()) {
      $this->sayOkay(sprintf("Initialised empty git repository in '%s'", $this->cwdGet()));
    }

    $this->cwdRestore();
  }

  /**
   * Add remote to git repository.
   *
   * @param string $dir
   *   Repository directory name.
   * @param string $url
   *   Remote URL.
   * @param string $name
   *   Name to add remote as. Default to self::GIT_REMOTE_NAME.
   */
  protected function gitAddRemote($dir, $url, $name = self::GIT_REMOTE_NAME) {
    $this->cwdSet($dir);

    $url = $this->gitResolveUrl($url);

    $res = $this
      ->taskExec('git')
      ->arg('remote')
      ->arg('add')
      ->arg($name)
      ->arg($url)
      ->run();

    if ($res->wasSuccessful()) {
      $this->sayOkay(sprintf("Added remote '%s' as '%s'", $url, self::GIT_REMOTE_NAME));
    }

    $this->cwdRestore();
  }

  /**
   * Pull git branch from remote.
   *
   * @param string $dir
   *   Repository directory name.
   * @param string $branch
   *   Branch name.
   * @param string $remote
   *   Name to add remote as. Defaults to self::GIT_REMOTE_NAME.
   */
  protected function gitPull($dir, $branch, $remote = self::GIT_REMOTE_NAME) {
    $res = $this->gitCommand($dir)
      ->arg('pull')
      ->arg('--tags')
      ->arg($remote)
      ->arg($branch)
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception(sprintf("Unable to check out code from remote branch '%s'", $branch));
    }

    $this->sayOkay(sprintf("Checked out code from remote branch '%s'", $branch));
  }

  /**
   * Push git branch from remote.
   *
   * @param string $dir
   *   Repository directory name.
   * @param string $branch
   *   Branch name.
   * @param string $remote
   *   Name to add remote as. Defaults to self::GIT_REMOTE_NAME.
   * @param bool $simulate
   *   Flag to simulate interaction with real repository.
   */
  protected function gitPush($dir, $branch = NULL, $remote = self::GIT_REMOTE_NAME, $simulate = FALSE) {
    $branch = $branch ? $branch : $this->gitGetCurrentBranch($dir);

    $git = $this->gitCommand($dir)
      ->arg('push')
      ->arg('--tags')
      ->arg($remote)
      ->arg('HEAD:' . $branch);

    if ($simulate) {
      $this->yell(sprintf("Deploy would run: '%s'", str_replace("'", '', $git->getCommand())));
      $res = new ResultData();
    }
    else {
      $res = $git->run();
    }

    if ($res->wasSuccessful()) {
      $this->sayOkay(sprintf("Pushed code to remote '%s' into branch '%s'", $remote, $branch));
    }
    else {
      throw new Exception(sprintf("Failed to push code to remote '%s' into branch '%s'", $remote, $branch));
    }
  }

  /**
   * Commit files to got repo.
   *
   * @param string $dir
   *   Repository directory.
   * @param string $message
   *   Commit message.
   * @param string $add
   *   Files string mask to add. Default to '--all' meaning all files.
   */
  protected function gitCommit($dir, $message, $add = '--all') {
    $res = $this->taskGitStack()
      ->stopOnFail()
      ->dir($dir)
      ->add($add)
      ->commit($message)
      ->run();

    if ($res->wasSuccessful()) {
      $this->sayOkay(sprintf('Committed files'));
      $this->writeln('Commit message:');
      $this->writeln($message);
    }
    else {
      throw new Exception($res->getMessage());
    }
  }

  /**
   * Get tags.
   *
   * @param string $location
   *   Repository location.
   *
   * @return array
   *   Array of tag names as keys and commit hashes of the commits that
   *   these tag reference as values.
   */
  protected function gitGetAllTags($location) {
    if (!$this->gitHasCommits($location)) {
      return [];
    }

    $git = $this->gitCommand($location);
    // Resolve both lightweight and annotated tags to their referenced objects.
    $git->rawArg("for-each-ref --format '%(objectname) %(objecttype) %(refname) %(*objectname) %(*objecttype) %(*refname)' refs/tags");
    $res = $git->run();

    if (!$res->wasSuccessful()) {
      throw new Exception('Unable to retrieve tags');
    }

    return $this->gitParseTagsOutput($res->getMessage());
  }

  /**
   * Get tags since the specified commit in the repository.
   *
   * @param string $location
   *   Repository location.
   * @param string $ref
   *   Commit reference.
   *
   * @return array
   *   Array of tags.
   */
  protected function gitGetTagsSinceCommit($location, $ref) {
    $tags = [];

    $commits = $this->gitGetCommitsSince($location, ['hash' => $ref]);
    $all_tags = $this->gitGetAllTags($location);
    foreach ($commits as $commit) {
      $tags_per_commit = array_intersect_key($all_tags, array_flip($commit['tags']));
      $tags = array_merge($tags, $tags_per_commit);
    }

    return $tags;
  }

  /**
   * Get tags from the last commit in the repository.
   *
   * @param string $location
   *   Repository location.
   *
   * @return array
   *   Array of tags.
   */
  protected function gitGetTagsFromLastCommit($location) {
    $tags = [];

    $commits = $this->gitGetCommits($location, [
      'order' => BuildArtefact::GIT_COMMITS_SORT_DESC,
      'limit' => 1,
    ]);

    $tags = count($commits) > 0 ? $this->gitGetTagsSinceCommit($location, $commits[0]['hash']) : $tags;

    return $tags;
  }

  /**
   * Helper to parse output from tags retrieving command.
   *
   * @param string|array $output
   *   Output as a string or array of strings.
   *
   * @return array
   *   Array of tag names as keys and commit hashes as values
   */
  protected function gitParseTagsOutput($output) {
    $output = is_array($output) ? $output : array_filter(preg_split('/\R/', $output));
    $list = [];
    foreach ($output as $line) {
      $regex = '/([0-9a-f]+)\scommit\srefs\/tags\/([^\^\s]+)/';
      preg_match_all($regex, $line, $matches);
      if (isset($matches[1][0]) && isset($matches[2][0])) {
        $list[$matches[2][0]] = $matches[1][0];
      }
    }

    return $list;
  }

  /**
   * Add git tags.
   *
   * @param string $dir
   *   Repository directory.
   * @param array $tags
   *   Array of tags to add.
   *
   * @throws \Exception
   *   If not all provided tags were added.
   */
  protected function gitAddTags($dir, array $tags) {
    $added_tags = [];
    foreach ($tags as $tag) {
      $res = $this->taskGitStack()
        ->stopOnFail()
        ->dir($dir)
        ->tag($tag)
        ->run();
      if ($res->wasSuccessful()) {
        $added_tags[] = $tag;
      }
    }

    $skipped_tags = array_diff($tags, $added_tags);
    if (count($skipped_tags) > 0) {
      throw new Exception(sprintf("Tag(s) '%s' were not added", implode(', ', $skipped_tags)));
    }

    $this->sayOkay(sprintf("Added tag(s) '%s'", implode(', ', $tags)));
  }

  /**
   * Find the first existing branch in the list.
   *
   * @param string $location
   *   Repository directory.
   * @param array $branches
   *   Array of branch names.
   *
   * @return string
   *   First existing branch.
   *
   * @throws \Exception
   *   When non of the provided branches exist.
   */
  protected function gitFindExistingBranch($location, array $branches) {
    foreach ($branches as $branch) {
      if ($this->gitBranchExists($location, $branch)) {
        return $branch;
      }
    }

    throw new \RuntimeException('None of provided git branches exist');
  }

  /**
   * Check that a git branch at location exists.
   *
   * @param string|bool $location
   *   Location of the remote repository.
   * @param string $branch
   *   Branch name to check.
   * @param bool $check_remote
   *   Flag to check remote branch. If FALSE, local branch will be checked.
   *
   * @return bool
   *   TRUE if provided branch exists, FALSE otherwise.
   */
  protected function gitBranchExists($location, $branch, $check_remote = TRUE) {
    $res = $this->gitCommand()
      ->arg('ls-remote')
      ->arg($location)
      ->arg('*/' . $branch)
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception(sprintf('Unable to check whether the branch "%s" exists at "%s"', $branch, $location));
    }

    $matches = array_filter(preg_split('/\R/', $res->getMessage()));

    return count($matches) > 0;
  }

  /**
   * Create branch at location.
   *
   * @param string|bool $location
   *   Location of the remote repository.
   * @param string $branch
   *   Branch name to create.
   *
   * @throws \Exception
   *   If there was an error creating a branch.
   */
  protected function gitCreateBranch($location, $branch) {
    $res = $this->gitCommand($location)
      ->rawArg('checkout -b ' . $branch)
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception(sprintf('Unable to create a new branch "%s" in location "%s"', $branch, $location));
    }
  }

  /**
   * Git current branch of the git repository.
   *
   * @param string $dir
   *   Repository directory.
   *
   * @return string
   *   Return current branch name.
   *
   * @throws \Exception
   *   If branch was not found.
   */
  protected function gitGetCurrentBranch($dir) {
    $res = $this->gitCommand($dir)
      ->arg('rev-parse')
      ->arg('--abbrev-ref')
      ->arg('HEAD')
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception('Unable to get current repository branch');
    }

    $this->cwdRestore();

    return trim($res->getMessage());
  }

  /**
   * Get first git commit in repository.
   *
   * @param string $location
   *   Repository location.
   * @param array $filter
   *   Array of additional filters to apply.
   *
   * @return array
   *   First commit in repository.
   */
  protected function gitGetFirstCommit($location, array $filter = []) {
    $commits = $this->gitGetCommits($location, $filter + ['from' => 1, 'to' => 1]);
    $commit = reset($commits);

    return $commit;
  }

  /**
   * Get last git commit in repository.
   *
   * @param string $location
   *   Repository location.
   * @param array $filter
   *   Array of additional filters to apply.
   *
   * @return array
   *   Last commit in repository.
   */
  protected function gitGetLastCommit($location, array $filter = []) {
    $commits = $this->gitGetCommits($location, $filter + ['limit' => 1, 'order' => self::GIT_COMMITS_SORT_DESC]);
    $commit = reset($commits);

    return $commit;
  }

  /**
   * Shorthand to get commits since specified commit.
   *
   * @param string $location
   *   Repository location.
   * @param array $commit
   *   Commit.
   * @param array $filter
   *   Array of additional filters to apply.
   *
   * @return array
   *   Array of commits.
   */
  protected function gitGetCommitsSince($location, array $commit, array $filter = []) {
    $commits = $this->gitGetCommits($location, $filter + ['from' => $commit['hash']]);

    return $commits;
  }

  /**
   * Get a list of git commits information.
   *
   * Use this method to retrieve any information about commits.
   *
   * @param string $location
   *   Repository location.
   * @param array $filters
   *   Array of filters:
   *   - from: (string|integer|null)
   *     If integer - number of commits from the beginning of the log.
   *     If string - used as commit hash.
   *     If null (default) - use first commit.
   *   - to: (string|integer|null)
   *     If integer - number of commits to the end of the log.
   *     If string - used as commit hash.
   *     If null (default) - use last commit.
   *   - limit: (integer|null) If specified, limit number of commits to this
   *     number. Counting starts from the beginning of result set and is based
   *     on $filter['order'].
   *   - order: (string) Order of commits by timestamp,
   *     self::GIT_COMMIT_SORT_DESC or self::GIT_COMMITS_SORT_ASC.
   *     Defaults to self::GIT_COMMIT_SORT_DESC.
   *   - message: (string) Filter by message substring.
   *
   * @return array
   *   Array of commit arrays.
   *
   * @throws \Exception
   *   If repository does not contain any commits.
   */
  protected function gitGetCommits($location, array $filters = NULL) {
    $list = [];

    if ($this->isGitRemote($location, 'uri')) {
      throw new Exception('Retrieving information about commits from remote is not yet implemented.');
    }

    $commits_count = $this->gitGetCommitsCount($location);

    $filters += [
      'from' => isset($filters['to']) && is_numeric($filters['to']) ? 1 : $this->gitGetFirstCommit($location)['hash'],
      'to' => isset($filters['from']) && is_numeric($filters['from']) ? $commits_count : 'HEAD',
      'limit' => NULL,
      'order' => self::GIT_COMMITS_SORT_ASC,
      'message' => NULL,
    ];

    $git = $this->gitCommand($location);

    $value_delim = '####';
    $eol_delim = '@@@@';
    $git_format = "%H$value_delim%ct$value_delim%s$value_delim%b$value_delim%d$eol_delim";
    if (!empty($filters['from']) && !empty($filters['to'])) {
      $git->arg('log')
        ->arg('--first-parent')
        ->arg('--pretty=' . $git_format)
        ->arg('--reverse');

      if (is_string($filters['from']) && is_string($filters['to'])) {
        if ($filters['from'] == $filters['to']) {
          $git->arg($filters['from']);
        }
        else {
          $git->arg($filters['from'] . '...' . $filters['to']);
        }
      }
      elseif (is_numeric($filters['from']) && is_numeric($filters['to'])) {
        $skip = max($commits_count - $filters['to'], 0);
        $git->arg('--skip=' . $skip);
        $max_count = min($filters['to'], $commits_count) - $filters['from'] + 1;
        $git->arg('--max-count=' . $max_count);
      }
      else {
        throw new Exception('Support for searching with parameters of different types is not yet implemented');
      }
    }

    if ($filters['message']) {
      $git->arg('--grep=' . $filters['message']);
    }

    $res = $git->run();

    if (!$res->wasSuccessful()) {
      throw new Exception(sprintf("Unable to get commits with specified filters: %s", PHP_EOL . json_encode($filters)));
    }

    foreach (explode($eol_delim . PHP_EOL, $res->getMessage()) as $line) {
      if (!empty($line)) {
        $commit = $this->gitParseOutput($line, $value_delim);
        $list[] = $commit;
      }
    }

    // Add missing 'from' commit as 'git log' does not return it.
    if (is_string($filters['from']) && ($filters['from'] != $filters['to'] || empty($list))) {
      $from_commit = $this->gitGetCommitByHash($location, $filters['from']);
      array_unshift($list, $from_commit);
    }

    if ($filters['order'] == self::GIT_COMMITS_SORT_DESC) {
      $list = array_reverse($list);
    }

    if (empty($list)) {
      throw new Exception(sprintf("Git repository '%s' does not contain any commits", $location));
    }

    if ($filters['limit']) {
      $list = array_slice($list, 0, $filters['limit']);
    }

    return $list;
  }

  /**
   * Get a commit information for a commit specified by hash.
   *
   * @param string $location
   *   Repository location.
   * @param string $hash
   *   Commit hash.
   *
   * @return array
   *   Commit information data.
   */
  protected function gitGetCommitByHash($location, $hash) {
    if ($this->isGitRemote($location, 'uri')) {
      throw new Exception('Retrieving information about commits from remote is not yet implemented.');
    }

    $delim_values = '####';
    $delim_eol = '!!!!';
    $git_format = "%H$delim_values%ct$delim_values%s$delim_values%b$delim_values%d$delim_eol";

    $res = $this->gitCommand($location)
      ->arg('log')
      ->arg('--pretty=' . $git_format)
      ->arg($hash)
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception(sprintf("Unable to get commit by hash %s", $hash));
    }

    foreach (explode($delim_eol, trim($res->getMessage())) as $line) {
      if (!empty($line)) {
        $list[] = $this->gitParseOutput($line, $delim_values);
      }
    }

    return reset($list);
  }

  /**
   * Get commit count.
   *
   * @param string $dir
   *   Repository directory.
   *
   * @return int
   *   Commit count.
   *
   * @throws \Exception
   *   If no abler to retrieve the value.
   */
  protected function gitGetCommitsCount($dir) {
    if (!$this->gitHasCommits($dir)) {
      return 0;
    }

    $res = $this->gitCommand($dir)
      ->arg('rev-list')
      ->arg('HEAD')
      ->arg('--first-parent')
      ->arg('--count')
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception('Unable to retrieve total count of commits');
    }

    return intval($res->getMessage());
  }

  /**
   * Check if there are changes in git repository.
   *
   * @param string $dir
   *   Repository directory.
   *
   * @return bool
   *   TRUE if at least one file was changed, FALSE otherwise.
   *
   * @throws \Exception
   *   If there is no repository in the specified directory.
   */
  protected function gitRepoHasChanged($dir) {
    $res = $this->gitCommand($dir)
      ->arg('status')
      ->arg('--short')
      ->run();

    if (!$res->wasSuccessful()) {
      throw new Exception('Unable to retrieve status of the repository');
    }

    return !empty($res->getMessage());
  }

  /**
   * Check if git repo has any commits.
   *
   * @param string $dir
   *   Repository directory.
   *
   * @return bool
   *   TRUE if there is at least one commit, FALSE otherwise.
   *
   * @throws \Exception
   *   If it is not possible to get the state of the repo.
   */
  protected function gitHasCommits($dir) {
    $git = $this->gitCommand($dir);
    $git->rawArg('log --all --format="%H" 2>&1');
    $res = $git->run();

    if (!$res->wasSuccessful()) {
      $output = trim($res->getMessage());
      // Different versions of Git may produce these expected messages.
      $expected_error_messages = [
        "fatal: bad default revision 'HEAD'",
        "fatal: your current branch 'master' does not have any commits yet",
      ];

      if (in_array($output, $expected_error_messages)) {
        return FALSE;
      }

      throw Exception('Unable to check if the repository has commits');
    }

    return TRUE;
  }

  /**
   * Parse git output into commit arrays.
   *
   * @param string $text
   *   Text from git output.
   * @param string $delim
   *   Delimiter used in the output format to separate fields.
   *
   * @return array
   *   Commits array with the following keys:
   *   - hash: (string) Array hash (SHA).
   *   - subject: (string) Array message subject.
   *   - description: (string) Array message description.
   *   - date: (int) Commit timestamp.
   *   - tag: (array) Array of tags (each tag is a string).
   */
  protected function gitParseOutput($text, $delim) {
    $parts = explode($delim, $text);

    if (count($parts) == 1) {
      // If this happens, some baseline components or logic was changed.
      throw new Exception('Unable to correctly parse output of git command');
    }

    if (!empty($parts[4])) {
      preg_match_all('/tag\:\s*([^\(\)\,]+)/', $parts[4], $matches);
      $tags = isset($matches[1]) ? $matches[1] : [];
    }
    else {
      $tags = [];
    }

    return [
      'hash' => trim($parts[0]),
      'date' => trim($parts[1]),
      'subject' => trim($parts[2]),
      'description' => isset($parts[3]) ? trim($parts[3]) : '',
      'tags' => $tags,
    ];
  }

  /**
   * Resolve git url based on the provided path.
   *
   * Note that this is a minimal implementation of resolver that should cover
   * 80% of the cases.
   *
   * @param string $path
   *   Path to repository in the one of the formats accepted by git.
   *
   * @return string
   *   Resolved path.
   *
   * @see https://git-scm.com/book/id/v2/Git-on-the-Server-The-Protocols
   */
  protected function gitResolveUrl($path) {
    if (strpos($path, 'file://') === TRUE) {
      $path = substr($path, strlen('file://'));
    }

    if (strpos($path, '@') === FALSE && filter_var($path, FILTER_VALIDATE_URL) === FALSE) {
      if (strpos($path, DIRECTORY_SEPARATOR) !== 0) {
        $path = $this->getAbsolutePath($path);
      }
    }

    return $path;
  }

  /**
   * @} "Git-related functionality"
   */

  /**
   * Print success message.
   *
   * Usually used to explicitly state that some action was successfully
   * executed.
   *
   * @param string $text
   *   Message text.
   */
  protected function sayOkay($text) {
    $color = 'green';
    $char = $this->decorationCharacter('V', '✔');
    $format = "<fg=white;bg=$color;options=bold>%s %s</fg=white;bg=$color;options=bold>";
    $this->writeln(sprintf($format, $char, $text));
  }

  /**
   * OOP wrapper around standard date() function.
   */
  protected function date($format) {
    return date($format);
  }

  /**
   * Replacement for PHP's `realpath` resolves non-existing paths.
   *
   * The main deference is that it does not return FALSE on non-existing paths.
   *
   * @param string $path
   *   Path that needs to be resolved.
   *
   * @return string
   *   Resolved path.
   *
   * @see https://stackoverflow.com/a/29372360/712666
   */
  protected function realpath($path) {
    // Whether $path is unix or not.
    $unipath = strlen($path) == 0 || $path{0} != '/';
    $unc = substr($path, 0, 2) == '\\\\' ? TRUE : FALSE;
    // Attempt to detect if path is relative in which case, add cwd.
    if (strpos($path, ':') === FALSE && $unipath && !$unc) {
      $path = getcwd() . DIRECTORY_SEPARATOR . $path;
      if ($path{0} == '/') {
        $unipath = FALSE;
      }
    }

    // Resolve path parts (single dot, double dot and double delimiters).
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = [];
    foreach ($parts as $part) {
      if ('.' == $part) {
        continue;
      }
      if ('..' == $part) {
        array_pop($absolutes);
      }
      else {
        $absolutes[] = $part;
      }
    }
    $path = implode(DIRECTORY_SEPARATOR, $absolutes);
    // Resolve any symlinks.
    if (function_exists('readlink') && file_exists($path) && linkinfo($path) > 0) {
      $path = readlink($path);
    }
    // Put initial separator that could have been lost.
    $path = !$unipath ? '/' . $path : $path;
    $path = $unc ? '\\\\' . $path : $path;

    return $path;
  }

}
