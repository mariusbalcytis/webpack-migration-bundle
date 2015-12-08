<?php

class CompileCest
{
    public function _before(FunctionalTester $I)
    {
        $I->cleanUp();
    }

    public function _after(FunctionalTester $I)
    {
        $I->cleanUp();
    }

    public function getNoErrorIfAssetsAreDumped(FunctionalTester $I)
    {
        $I->runCommand('maba_webpack.command.setup');
        $I->seeFileFound(__DIR__ . '/Fixtures/package.json');
        $I->seeFileFound(__DIR__ . '/Fixtures/app/config/webpack.config.js');
        $I->runNpmCommand('npm install imports-loader --save-dev');

        $I->runCommand('maba_webpack.command.compile');
        $I->seeCommandStatusCode(0);
        $I->seeInCommandDisplay('webpack');
        $I->dontSeeInCommandDisplay('error');

        $I->amOnPage('/');
        $I->canSeeResponseCodeIs(200);
        $I->dontSee('Manifest file not found');

        $I->findAndOpenLink('link#first_css', 'href');
        $I->canSeeInThisFile('named1-app-css-content');
        $I->canSeeInThisFile('named1-bundle-css-content');
        $I->canSeeInThisFile('source1-app-css-content');
        $I->canSeeInThisFile('source1-bundle-css-content');

        $I->findAndOpenLink('link#second_css', 'href');
        $I->canSeeInThisFile('named2-simple-css-content');
        // Including css not supported from named assets
//        $I->canSeeInThisFile('named2-with-require-css-content');
//        $I->canSeeInThisFile('from-named2-css-content');
        $I->canSeeInThisFile('source2-simple-css-content');
        $I->canSeeInThisFile('source2-with-require-css-content');
        $I->canSeeInThisFile('from-source2-css-content');

        $I->findAndOpenLink('script#first_js', 'src');
        $I->canSeeInThisFile('named1-app-js-content');
        $I->canSeeInThisFile('named1-bundle-js-content');
        $I->canSeeInThisFile('source1-app-js-content');
        $I->canSeeInThisFile('source1-bundle-js-content');

        $I->findAndOpenLink('script#second_js', 'src');
        $I->canSeeInThisFile('named2-simple-js-content');
        $I->canSeeInThisFile('named2-with-require-js-content');
        $I->canSeeInThisFile('from-named2-js-content');
        $I->canSeeInThisFile('source2-simple-js-content');
        $I->canSeeInThisFile('source2-with-require-js-content');
        $I->canSeeInThisFile('from-source2-js-content');
        $I->canSeeInThisFile('glob1-js-content');
        $I->canSeeInThisFile('glob2-js-content');
        $I->canSeeInThisFile('glob3-js-content');

        $I->findAndOpenLink('script#webpack_js', 'src');
        $I->canSeeInThisFile('main-webpack-js-content');
        $I->canSeeInThisFile('from-webpack-js-content');

        $I->amOnPage('/en');
        $I->canSeeResponseCodeIs(200);
        $I->findAndOpenLink('script', 'src');
        $I->canSeeInThisFile('language-en-js-content');
        $I->dontSeeInThisFile('language-lt-js-content');

        $I->amOnPage('/lt');
        $I->canSeeResponseCodeIs(200);
        $I->findAndOpenLink('script', 'src');
        $I->canSeeInThisFile('language-lt-js-content');
        $I->dontSeeInThisFile('language-en-js-content');
    }
}
