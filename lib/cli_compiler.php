#!/usr/bin/php

Welcome to the interactive Doctrine Compiler 0.0.1 (Beta).

WARNING: You're on your own - this script modifies your
filesystem and has not been tested on Windows (yet).
<?php
if (in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

Usage:
  <?php echo $argv[0]; ?> [path]

  [path] is the path to Doctrine's lib directory. This
  is normally the directory containing the core
  Doctrine class, 'Doctrine.php'.

While the program is running:

  Default responses to questions are capitalized. Just
  hit enter to provide a question's default response.

  You can quit the program at any time by pressing 'q'.

<?php
    exit;
}

if ($argc > 1) {
    $doctrinePath = $argv[1];
}

$drivers = array(
    'Db2',
    'Firebird',
    'Informix',
    'Mssql',
    'Mysql',
    'Oracle',
    'Pgsql',
    'Sqlite'
);

function addIncludePath($path) {
    set_include_path(
        $path . PATH_SEPARATOR . get_include_path()
    );
}

function showMessage($message) {
    echo "\n$message\n";
}

function quit($message = 'Program Aborted.') {
    if ($message) {
        showMessage($message);
    }
    exit;
}

function autoQuit($answer) {
    if (trim(strtolower($answer)) == 'q') {
        quit();
    }
}

function ask(
    $question,
    $defaultOption = 'y',
    $displayOptions = array('yes', 'no', 'quit'),
    $autoValidate = true)
{
    $prompt = "\n$question";
    if ($displayOptions) {
        $optionCount = count($displayOptions);
        if ($defaultOption) {
            for ($i = 0; $i < $optionCount; $i++) {
                if ($displayOptions[$i][0] == $defaultOption) {
                    $displayOptions[$i] = ucfirst($displayOptions[$i]);
                    break;
                }
            }
        }
        if ($autoValidate) {
            for ($i = 0; $i < $optionCount; $i++) {
                $validation[] = strtolower($displayOptions[$i][0]);
            }
        }
        $prompt .= "\n[" . implode('/', $displayOptions) . "] ";
    }
    $done = false;
    while (!$done) {
        echo $prompt;
        $input = trim(fgets(STDIN));
        if (!$input && $defaultOption) {
            $input = $defaultOption;
        }
        if (isset($validation)) {
            if ($input) {
                $input = strtolower($input[0]);
            }
            if (array_search($input, $validation) !== false) {
                return $input;
            } else {
                echo "\n" . 'The answer "' . $input . '" is invalid.  It must be one of "' . implode('", "', $validation) . '".' . "\n";
            }
        } else {
            return $input;
        }
    }
}

// Enable error reporting
if (($answer = ask("Would you like to turn on error reporting?", 'n')) == 'y') {
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}
autoQuit($answer);

// Get Doctrine's library path
$usablePath = false;
while (!$usablePath) {
    if (!isset($doctrinePath)) {
        $doctrinePath = dirname(__FILE__);
    }
    $path = ask("Please enter the path to Doctrine's lib directory:", $doctrinePath, array($doctrinePath), false);
    autoQuit($path);
    try {
        addIncludePath($path);
        include_once 'Doctrine.php';
        spl_autoload_register(array('Doctrine', 'autoload'));
        $usablePath = true;
    } catch (Exception $e) {
        showMessage("The path '$path' does not seem to contain the expected Doctrine class ('Doctrine.php').");
        if (($answer = ask("Would you like to specify another path?")) != 'y') {
            quit();
        }
    }
}

$includeDrivers = array();
$excludeDrivers = array();

// Determine driver support
foreach ($drivers as $driver) {
    if (($answer = ask("Would you like to enable support for $driver?")) == 'y') {
        $includeDrivers[] = $driver;
    } else {
        $excludeDrivers[] = $driver;
    }
    autoQuit($answer);
}

// Verify driver support
if (!count($includeDrivers)) {
    showMessage("You have not selected any drivers. Normally this is not a good idea, unless you know what you're doing.");
    if (($answer = ask("Are you sure you want to compile without any drivers?")) != 'y') {
        quit();
    }
    autoQuit($answer);
}

// 'Compile' Doctrine...
showMessage("Trying to set the PHP memory limit to 18MB...");
ini_set('memory_limit', '18M');
if (ini_get('memory_limit') != '18M') {
    showMessage("WARNING: The program could not set the PHP memory limit to 18MB. The compilation might fail.");
    if (($answer = ask("Do you still want to continue?")) != 'y') {
        quit;
    }
} else {
    showMessage("PHP memory limit adjusted successfully.");
}


$target = './Doctrine.compiled.php';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);

foreach ($it as $file) {
    $e = explode('.', $file->getFileName());

    // we don't want to require versioning files
    if (end($e) === 'php' && strpos($file->getFileName(), '.inc') === false) {
        require_once $file->getPathName();
    }
}

$classes = array_merge(get_declared_classes(), get_declared_interfaces());

$ret     = array();

foreach ($classes as $class) {
    $e = explode('_', $class);

    if ($e[0] !== 'Doctrine') {
        continue;
    }

    // Skip excluded drivers
    $skipClass = false;
    foreach ($excludeDrivers as $excludeDriver) {
        if (in_array($excludeDriver, $e)) {
            $skipClass = true;
            break;
        }
    }
    if ($skipClass) {
        echo "\nExcluding -> $class";
        continue;
    }

    $refl  = new ReflectionClass($class);
    $file  = $refl->getFileName();

    echo "\nIncluding -> $file";

    $lines = file($file);

    $start = $refl->getStartLine() - 1;
    $end   = $refl->getEndLine();

    $ret = array_merge($ret, array_slice($lines, $start, ($end - $start)));
}

if ($target == null) {
    $target = $path . DIRECTORY_SEPARATOR . 'Doctrine.compiled.php';
}

// first write the 'compiled' data to a text file, so
// that we can use php_strip_whitespace (which only works on files)
$fp = @fopen($target, 'w');

if ($fp === false) {
    throw new Exception("Couldn't write compiled data. Failed to open $target");
}
fwrite($fp, "<?php ". implode('', $ret));
fclose($fp);

$stripped = php_strip_whitespace($target);
$fp = @fopen($target, 'w');
if ($fp === false) {
    throw new Exception("Couldn't write compiled data. Failed to open $file");
}
fwrite($fp, $stripped);
fclose($fp);

// Say bye...
showMessage("\nCompilation Finished.");
showMessage("Thank you for using the interactive Doctrine Compiler. Have fun following the Doctrine :)\n");


?>