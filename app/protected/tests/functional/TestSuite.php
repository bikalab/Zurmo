<?php
    /*********************************************************************************
     * Zurmo is a customer relationship management program developed by
     * Zurmo, Inc. Copyright (C) 2011 Zurmo Inc.
     *
     * Zurmo is free software; you can redistribute it and/or modify it under
     * the terms of the GNU General Public License version 3 as published by the
     * Free Software Foundation with the addition of the following permission added
     * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
     * IN WHICH THE COPYRIGHT IS OWNED BY ZURMO, ZURMO DISCLAIMS THE WARRANTY
     * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
     *
     * Zurmo is distributed in the hope that it will be useful, but WITHOUT
     * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
     * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
     * details.
     *
     * You should have received a copy of the GNU General Public License along with
     * this program; if not, see http://www.gnu.org/licenses or write to the Free
     * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
     * 02110-1301 USA.
     *
     * You can contact Zurmo, Inc. with a mailing address at 113 McHenry Road Suite 207,
     * Buffalo Grove, IL 60089, USA. or at email address contact@zurmo.com.
     ********************************************************************************/

    $basePath = realpath(dirname(__FILE__) . '../../../../../');

    define('SELENIUM_SERVER_PATH', 'd:\Selenium\selenium-server-standalone-2.0b1.jar');
    define('TEST_BASE_URL', 'http://localhost/app/');
    define('TEST_RESULTS_URL', 'http://localhost/testresults/functional/');
    define('TEST_RESULTS_PATH', $basePath . '\testresults\functional\\');
    define('SELENIUM_SERVER_PORT', 4048);
    define('BROWSERS_TO_RUN', 'All');
    require_once('File/Iterator/Factory.php');
    class TestSuite
    {
        public static function run()
        {
            global $argv, $argc;

            $usage = "\n"                                                                                             .
                     "  Usage: php [options] TestSuite.php <All|Misc|moduleName|TestClassName> [options]\n"           .
                     "\n"                                                                                             .
                     "    All           Run all tests.\n"                                                             .
                     "    Framework     Run all tests in framework/tests/functional.\n"                               .
                     "    Misc          Run the test suites in app/protected/tests/functional.\n"                     .
                     "    moduleName    Run the test suites in app/protected/modules/moduleName/tests/functional.\n"  .
                     "    TestClassName Run the tests in TestClassName.html, wherever that happens to be.\n"          .
                     "    options\n"                                                                                  .
                     "    -p            port Example: -p4044\n"                                                       .
                     "    -h            host Example: -hhttp://www.sitetotest/app/\n"                                 .
                     "    -b            browser <*firefox|*iexplore> if not specified, will run all in browsers \n"   ;
                     "                  Example: -b*firefox \n"                                                       ;
                     "\n"                                                                                             .
                     "  Examples:\n"                                                                                  .
                     "\n"                                                                                             .
                     "    php TestSuiteSelenium.php accounts (Run the tests in the Accounts module.)\n"               .
                     "    php TestSuiteSelenium.php RedBeanModelTest   (Run the test suite RedBeanModelTest.html.)\n" .
                     "\n"                                                                                             .
                     "  Note:\n"                                                                                      .
                     "\n"                                                                                             ;

            if ($argv[0] != 'TestSuite.php')
            {
                echo $usage;
                exit;
            }
            else
            {
                $whatToTest = $argv[1];
            }
            $whatToTestIsModuleDir = self::isWhatToTestAModule($whatToTest);
            $suiteNames          = array();
            $htmlTestSuiteFiles  = array();
            if ($whatToTest != 'Misc' && !$whatToTestIsModuleDir)
            {
                $compareToTest = $whatToTest;
                if ($whatToTest == 'Framework')
                {
                    $compareToTest = null;
                }
                $frameworkTestSuiteDirectory = '../../extensions/zurmoinc/framework/tests/functional';
                $htmlTestSuiteFiles = self::buildSuiteFromSeleneseDirectory(
                    $htmlTestSuiteFiles, $frameworkTestSuiteDirectory, $compareToTest);
            }
            $moduleDirectoryName = '../../modules';
            if (is_dir($moduleDirectoryName))
            {
                $moduleNames = scandir($moduleDirectoryName);
                foreach ($moduleNames as $moduleName)
                {
                    if ($moduleName != '.' &&
                        $moduleName != '..')
                    {
                        $moduleFunctionalTestDirectoryName = "$moduleDirectoryName/$moduleName/tests/functional";
                        if (is_dir($moduleFunctionalTestDirectoryName))
                        {
                            if ($whatToTest          == 'All'        ||
                                // Allow specifying 'Users' for the module name 'users'.
                                $whatToTest          == $moduleName  ||
                                strtolower($whatToTest) == $moduleName  || !$whatToTestIsModuleDir)
                            {
                                if ($whatToTest          == $moduleName || strtolower($whatToTest) == $moduleName)
                                {
                                    $compareToTest = null;
                                }
                                else
                                {
                                    $compareToTest = $whatToTest;
                                }
                                $htmlTestSuiteFiles = self::buildSuiteFromSeleneseDirectory(
                                    $htmlTestSuiteFiles, $moduleFunctionalTestDirectoryName, $compareToTest);
                            }
                        }
                    }
                }
            }
            if ($whatToTest == 'All' || $whatToTest == 'Misc' || !$whatToTestIsModuleDir)
            {
                $compareToTest = $whatToTest;
                if ($whatToTest == 'Misc')
                {
                    $compareToTest = null;
                }
                $htmlTestSuiteFiles = self::buildSuiteFromSeleneseDirectory($htmlTestSuiteFiles, '.', $compareToTest);
            }
            if (count($htmlTestSuiteFiles) == 0)
            {
                echo $usage;
                echo "  No tests found for '$whatToTest'.\n\n";
                exit;
            }
            echo 'Suites to run:' . "\n";
            foreach ($htmlTestSuiteFiles as $pathToSuite)
            {
                if (in_array(basename($pathToSuite), $suiteNames))
                {
                    echo 'Cannot run tests because there are 2 test suites with the same name.' . "\n";
                    echo 'The duplicate found is here: ' . $pathToSuite . "\n";
                    exit;
                }
                $suiteNames[] = basename($pathToSuite);
                echo $pathToSuite . "\n";
            }
            echo 'Running Test Suites using Selenium RC v2:' . "\n";
            $browsersToRun = self::resolveBrowserFromParameter();
            foreach ($browsersToRun as $browserId => $browserDisplayName)
            {
                foreach ($htmlTestSuiteFiles as $pathToSuite)
                {
                    echo 'Running test suite: ';
                    echo $pathToSuite . "\n";
                    $host = self::resolveHostFromParameterAndConstant();
                    $hostFilePart = str_replace('http://', '', $host);
                    $hostFilePart = str_replace('https://', '', $hostFilePart);
                    $hostFilePart = str_replace('/', '', $hostFilePart);
                    $hostFilePart = $hostFilePart . '.';
                    $testResultFileNamePrefix = str_replace('../', '', $pathToSuite);
                    $testResultFileNamePrefix = str_replace('/',   '.', $testResultFileNamePrefix);
                    $testResultFileNamePrefix = str_replace('\\',  '.', $testResultFileNamePrefix);
                    $testResultFileNamePrefix = str_replace('..', '', $testResultFileNamePrefix);
                    $testResultFileNamePrefix = str_replace('.html', '', $testResultFileNamePrefix);
                    $testResultsFileName = $testResultFileNamePrefix . '.' . str_replace(' ', '', $browserDisplayName) . '.TestResults.html';
                    $finalTestResultsPath = TEST_RESULTS_PATH . $hostFilePart . $testResultsFileName;
                    $finalCommand  = 'java -jar "' . SELENIUM_SERVER_PATH .'" ';
                    $finalCommand .= '-port ' . self::resolvePortFromParameterAndConstant();
                    $finalCommand .= ' -htmlSuite ' . $browserId . ' ';
                    $finalCommand .= $host . ' ' . realPath($pathToSuite) . ' ' . $finalTestResultsPath;
                    //echo $finalCommand . "\n";
                    exec($finalCommand);
                }
            }
            echo 'Functional Run Complete.' . "\n";
            self::updateTestResultsSummaryFile();
        }

        public static function buildSuiteFromSeleneseDirectory($htmlTestSuiteFiles, $directoryName, $whatToTest = null)
        {
            $files = array_merge(
              self::getSeleneseFiles($directoryName, '.html')
            );
            foreach ($files as $file)
            {
                if (!strpos($file, 'TestSuite') === false)
                {
                    if ( $whatToTest == null || $whatToTest == 'All' ||
                        ($whatToTest . '.html' == basename($file) && $whatToTest != null))
                    {
                        $htmlTestSuiteFiles[] = $file;
                    }
                }
            }
            return $htmlTestSuiteFiles;
        }

        /**
         * @param  string $directory
         * @param  string $suffix
         * @return array
         * @since  Method available since Release 3.3.0
         */
        protected static function getSeleneseFiles($directory, $suffix)
        {
            $files    = array();
            $iterator = File_Iterator_Factory::getFileIterator($directory, $suffix);
            foreach ($iterator as $file)
            {
                if (!in_array($file, $files))
                {
                    $files[] = (string)$file;
                }
            }
            return $files;
        }

        /**
         * @return true if what to test is a module directory
         */
        protected static function isWhatToTestAModule($whatToTest)
        {
            $moduleDirectoryName = '../../modules';
            if (is_dir($moduleDirectoryName))
            {
                $moduleNames = scandir($moduleDirectoryName);
                foreach ($moduleNames as $moduleName)
                {
                    if ($moduleName != '.' &&
                        $moduleName != '..')
                    {
                        $moduleFunctionalTestDirectoryName = "$moduleDirectoryName/$moduleName/tests/functional";
                        if (is_dir($moduleFunctionalTestDirectoryName))
                        {
                            if (// Allow specifying 'Users' for the module name 'users'.
                                $whatToTest          == $moduleName  ||
                                ucfirst($whatToTest) == $moduleName)
                            {
                                return true;
                            }
                        }
                    }
                }
            }
            return false;
        }

        protected static function resolvePortFromParameterAndConstant()
        {
            global $argv, $argc;

            for ($i = 0; $i < ($argc); $i++)
            {
                if (substr($argv[$i], 0, 2) == '-p')
                {
                    return substr($argv[$i], 2);
                }
            }
            return SELENIUM_SERVER_PORT;
        }

        protected static function resolveHostFromParameterAndConstant()
        {
            global $argv, $argc;

            for ($i = 0; $i < ($argc); $i++)
            {
                if (substr($argv[$i], 0, 2) == '-h')
                {
                    return substr($argv[$i], 2);
                }
            }
            return TEST_BASE_URL;
        }

        protected static function resolveBrowserFromParameter()
        {
            global $argv, $argc;

            $browserData = self::getBrowsersData();

            for ($i = 0; $i < ($argc); $i++)
            {
                if (substr($argv[$i], 0, 2) == '-b')
                {
                    $browsersToRun = substr($argv[$i], 2);
                    if ($browsersToRun == BROWSERS_TO_RUN)
                    {
                        return $this->getBrowsersData();
                    }
                    if (!in_array($browsersToRun,
                        array('*iexplore', '*firefox', '*googlechrome')))
                    {
                        echo 'Invalid Browser specified.' . "\n";
                        echo 'Specified Browser: ' . $browsersToRun . "\n";
                        exit;
                    }
                    foreach ($browserData as $id => $name)
                    {
                        if ($id == $browsersToRun)
                        {
                            return array($id => $name);
                        }
                    }
                }
            }
            return self::getBrowsersData();
        }

        protected static function getBrowsersData()
        {
            return array(
                '*firefox'      => 'FireFox',
                '*iexplore'     => 'Internet Explorer',
                '*googlechrome' => 'Chrome',
            );
        }

        protected static function updateTestResultsSummaryFile()
        {
            $data = array();
            if (is_dir(TEST_RESULTS_PATH))
            {
                $resultsNames = scandir(TEST_RESULTS_PATH);
                foreach ($resultsNames as $resultFile)
                {
                    if ($resultFile != '.' &&
                        $resultFile != '..' &&
                        $resultFile != 'Summary.html')
                    {
                        $data[] = array(
                            'fileName' => $resultFile,
                            'modifiedDate' => date ("F d Y H:i:s.", filemtime(TEST_RESULTS_PATH . $resultFile)),
                            'status'   => self::getResultFileStatusByFileName($resultFile),
                            'browser'       => self::getResultFileBrowserByFileName($resultFile),
                        );
                    }
                }
            }
            self::makeResultsSummaryFile($data);
        }

        protected static function getResultFileStatusByFileName($resultFile)
        {
            $contents = file_get_contents(TEST_RESULTS_PATH . $resultFile);
            $contents = str_replace('"', '', $contents);
            $contents = strtolower($contents);

            $pieces = explode('id=suitetable', $contents); // Not Coding Standard
            if (!empty($pieces[1]))
            {
                $pieces = explode('</table>', $pieces[1]);
                $pieces = explode('<tr class=title', $pieces[0]); // Not Coding Standard
                $pieces = explode('>', $pieces[1]);
                return trim($pieces[0]);
            }
            return 'Unknown';
        }

        protected static function getResultFileBrowserByFileName($resultFile)
        {
            if (stristr($resultFile, 'firefox'))
            {
                return 'Firefox';
            }
            elseif (stristr($resultFile, 'internetexplorer'))
            {
                return 'IE';
            }
            elseif (stristr($resultFile, 'chrome'))
            {
                return 'Chrome';
            }
            return 'Unknown';
        }

        protected static function makeResultsSummaryFile($data)
        {
            $fileName = TEST_RESULTS_PATH . 'Summary.html';
            $content = '<html>';
            $content .= '<table border="1" width="100%">'                               . "\n";
            $content .= '<tr>'                                                          . "\n";
            $content .= '<td>Status</td>'                                               . "\n";
            $content .= '<td>Browser</td>'                                              . "\n";
            $content .= '<td>Date</td>'                                                 . "\n";
            $content .= '<td>File</td>'                                                 . "\n";
            $content .= '</tr>'                                                         . "\n";
            foreach ($data as $info)
            {
                $link = '<a href="' . TEST_RESULTS_URL . $info['fileName'] . '">' . $info['fileName'] . '</a>';
                $statusColor = 'bgcolor="red"';
                if ($info['status']=='status_passed')
                {
                    $statusColor = 'bgcolor="green"';
                }
                $content .= '<tr>'                                                      . "\n";
                $content .= '<td ' . $statusColor . '>' . $info['status']   . '</td>'   . "\n";
                $content .= '<td>' . $info['browser']                       . '</td>'   . "\n";
                $content .= '<td>' . $info['modifiedDate']                  . '</td>'   . "\n";
                $content .= '<td>' . $link                                  . '</td>'   . "\n";
                $content .= '</tr>'                                                     . "\n";
            }
            $content .= '</table>'                                                      . "\n";
            $content .= '</html>'                                                       . "\n";

            if (is_writable(TEST_RESULTS_PATH))
            {
                if (!$handle = fopen($fileName, 'w'))
                {
                     echo "Cannot open file ($fileName)";
                     exit;
                }

                // Write $somecontent to our opened file.
                if (fwrite($handle, $content) === false)
                {
                    echo "Cannot write to file ($filename)";
                    exit;
                }
                fclose($handle);
            }
            else
            {
                echo "The file $fileName is not writable";
            }
        }
    }
    $testRunner = new TestSuite();
    $testRunner->run();
?>