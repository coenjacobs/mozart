<?php
/**
 * Copies license files from original folders.
 * Edits Phpdoc to record the file was changed.
 *
 * MIT states: "The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software."
 *
 * GPL states: "You must cause the modified files to carry prominent notices stating
 * that you changed the files and the date of any change."
 *
 * @see https://github.com/coenjacobs/mozart/issues/87
 *
 * @author BrianHenryIE
 */

namespace BrianHenryIE\Strauss;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Licenser
{

    protected string $workingDir;

    protected array $dependencies;

    // The author of the current project who is running Strauss to make the changes to the required libraries.
    protected string $author;

    protected string $targetDirectory;

    /**
     * An array of files relative to the project vendor folder.
     *
     * @var string[]
     */
    protected array $discoveredLicenseFiles = array();

    /** @var Filesystem */
    protected $filesystem;


    /**
     * Licenser constructor.
     * @param string $workingDir
     * @param array $dependencies Whose folders are searched for existing license.txt files.
     * @param string $author To add to each modified file's header
     */
    public function __construct(StraussConfig $config, string $workingDir, array $dependencies, string $author)
    {
        $this->workingDir = $workingDir;
        $this->dependencies = $dependencies;
        $this->author = $author;

        $this->targetDirectory = $config->getTargetDirectory();

        $this->filesystem = new Filesystem(new Local($workingDir));
    }

    public function copyLicenses(): void
    {
        $this->findLicenseFiles();

        foreach ($this->getDiscoveredLicenseFiles() as $licenseFile) {
            $targetLicenseFile = $this->targetDirectory . $licenseFile;

            $targetLicenseFileDir = dirname($targetLicenseFile);

            // Don't try copy it if it's already there.
            if ($this->filesystem->has($targetLicenseFile)) {
                continue;
            }

            // Don't add licenses to non-existent directories – there were no files copied there!
            if (! $this->filesystem->has($targetLicenseFileDir)) {
                continue;
            }

            $this->filesystem->copy(
                'vendor' . DIRECTORY_SEPARATOR . $licenseFile,
                $targetLicenseFile
            );
        }
    }


    /**
     * @see https://www.phpliveregex.com/p/A5y
     */
    public function findLicenseFiles(?Finder $finder = null)
    {

        // Include all license files in the dependency path.
        $finder = $finder ?? new Finder();

        // TODO: read 'vendor' from composer.json.
        $prefixToRemove = 'vendor' . DIRECTORY_SEPARATOR;

        /** @var ComposerPackage $dependency */
        foreach ($this->dependencies as $dependency) {
            $packagePath = 'vendor' . DIRECTORY_SEPARATOR . $dependency->getPath();

            // If packages happen to have their vendor dir, i.e. locally required packages, don't included the licenses
            // from their vendor dir (they should be included otherwise anyway).
            // $dependency->getVendorDir()
            $finder->files()->in($packagePath)->followLinks()->exclude(array( 'vendor' ))->name('/^.*licen.e.*/i');

            /** @var \SplFileInfo $foundFile */
            foreach ($finder as $foundFile) {
                $filePath = $foundFile->getPathname();

                $relativeFilepath = str_replace($prefixToRemove, '', $filePath);

                // Replace multiple \ and/or / with OS native DIRECTORY_SEPARATOR.
                $relativeFilepath = preg_replace('#[\\\/]+#', DIRECTORY_SEPARATOR, $relativeFilepath);

                $this->discoveredLicenseFiles[$relativeFilepath] = $dependency->getName();
            }
        }
    }

    public function getDiscoveredLicenseFiles(): array
    {
        return array_keys($this->discoveredLicenseFiles);
    }

    /**
     * @param array<string, ComposerPackage> $modifiedFiles
     */
    public function addInformationToUpdatedFiles(array $modifiedFiles)
    {

        // e.g. "25-April-2021".
        $date = gmdate("d-F-Y", time());

        foreach ($modifiedFiles as $relativeFilePath => $package) {
            $filepath = $this->targetDirectory . $relativeFilePath;

            $packageLicense = $package->getLicense();

            // Throws an exception, but unlikely to happen.
            $contents = $this->filesystem->read($filepath);

            $updatedContents = $this->addChangeDeclarationToPhpString($contents, $date, $packageLicense);

            if ($updatedContents !== $contents) {
                $this->filesystem->put($filepath, $updatedContents);
            }
        }
    }

    /**
     * Given a php file as a string, edit it's header phpdoc, or add a header, to include:
     *
     * "Modified by {author} on {date} using Strauss.
     * @see https://github.com/BrianHenryIE/strauss"
     *
     * Should probably include the original license in each file since it'll often be a mix, with the parent
     * project often being a GPL WordPress plugin.
     *
     * Find the string between the end of php-opener and the first valid code.
     * First valid code will be a line whose first non-whitespace character is not / or * ?... NO!
     * If the first non whitespace string after php-opener is multiline-comment-opener, find the
     * closing multiline-comment-closer
     * / If there's already a comment, work within that comment
     * If there is no mention in the header of the license already, add it.
     * Add a note that changes have been made.
     *
     * @param string $phpString Code.
     */
    public function addChangeDeclarationToPhpString(
        string $phpString,
        string $modifiedDate,
        string $packageLicense
    ) : string {

        $author = $this->author;

        $licenseDeclaration = "@license {$packageLicense}";
        $modifiedDeclaration = "Modified by {$author} on {$modifiedDate} using Strauss.";
        $straussLink = "@see https://github.com/BrianHenryIE/strauss";

        // php-open followed by some whitespace and new line until the first ...
        $noCommentBetweenPhpOpenAndFirstCodePattern = '~<\?php[\s\n]*[\w]+~';

        $multilineCommentCapturePattern = '
            ~                        # Start the pattern
            (
            <\?php[\S\s]*            #  match the beginning of the files php-open and following whitespace
            )
            (
            \*[\S\s.]*               # followed by a multline-comment-open
            )  
            (
            \*/                      # Capture the multiline-comment-close separately
            )             
            ~Ux';                          // U: Non-greedy matching, x: ignore whitespace in pattern.


        $replaceInMultilineCommentFunction = function ($matches) use (
            $licenseDeclaration,
            $modifiedDeclaration,
            $straussLink
        ) {

            // Find the line prefix and use it, i.e. could be none, asterisk or space-asterisk.
            $commentLines = explode("\n", $matches[2]);

            if (isset($commentLines[1])&& 1 === preg_match('/^([\s\\\*]*)/', $commentLines[1], $output_array)) {
                $lineStart = $output_array[1];
            } else {
                $lineStart = ' * ';
            }

            $appendString = "*\n";

            // If the license is not already specified in the header, add it.
            if (false === strpos($matches[2], 'licen')) {
                $appendString .= "{$lineStart}{$licenseDeclaration}\n";
            }

            $appendString .= "{$lineStart}{$modifiedDeclaration}\n";
            $appendString .= "{$lineStart}{$straussLink}\n";

            $commentEnd =  rtrim(rtrim($lineStart, ' '), '*').'*/';

            $replaceWith = $matches[1] . $matches[2] . $appendString . $commentEnd;

            return $replaceWith;
        };

        // If it's a simple case where there is no existing header, add the existing license.
        if (1 === preg_match($noCommentBetweenPhpOpenAndFirstCodePattern, $phpString)) {
            $modifiedComment = "/**\n * {$licenseDeclaration}\n *\n * {$modifiedDeclaration}\n * {$straussLink}\n */";
            $updatedPhpString = str_replace('<?php', "<?php\n". $modifiedComment, $phpString);
        } else {
            $updatedPhpString = preg_replace_callback(
                $multilineCommentCapturePattern,
                $replaceInMultilineCommentFunction,
                $phpString,
                1
            );
        }

        return $updatedPhpString;
    }
}
