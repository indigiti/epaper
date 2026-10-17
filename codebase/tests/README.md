# Reproducing processing regression checks

Use a disposable checkout, isolated MariaDB database named epdi_release_test, PHP CLI with PDO MySQL, mbstring, Imagick and DOM, pdftoppm, Tesseract English data, and /bin/sleep. Do not run against production. The test creates fixture tenants/editions/jobs and files inside the checkout.

Create the empty epdi_release_test database, then set EPDI_TEST_DATABASE=epdi_release_test, EPDI_TEST_DSN=mysql:host=127.0.0.1;port=13316;dbname=epdi_release_test, EPDI_TEST_USER and EPDI_TEST_PASS. Run `php tests/processing-regression.php`.

Set EPDI_TEST_PDF to a one-page English fixture PDF to run actual rendering/OCR. Set EPDI_TEST_LAUNCH=1 to test the detached web-launch path; this test expects MariaDB on 127.0.0.1:13316, a PHP CLI with the same modules on PATH, no existing config/config.php, and writes/removes disposable config. Never enable it inside a deployed installation. After tests, discard the checkout and test database.

Local release tests ran with PHP 8.3.6, MariaDB 10.11.14, Imagick 3.7.0 and installed pdftoppm/Tesseract, queue.driver=rabbitmq, no broker connection and Redis disabled. See release/RELEASE-CHECK-2.0.1.4.txt for results and limits.
