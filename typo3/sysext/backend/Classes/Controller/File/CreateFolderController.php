<?php
namespace TYPO3\CMS\Backend\Controller\File;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Script Class for the create-new script; Displays a form for creating up to 10 folders or one new text file
 */
class CreateFolderController
{
    /**
     * @var int
     */
    public $folderNumber = 10;

    /**
     * document template object
     *
     * @var DocumentTemplate
     */
    public $doc;

    /**
     * Name of the filemount
     *
     * @var string
     */
    public $title;

    /**
     * @var int
     */
    public $number;

    /**
     * Set with the target path inputted in &target
     *
     * @var string
     */
    public $target;

    /**
     * The folder object which is  the target directory
     *
     * @var \TYPO3\CMS\Core\Resource\Folder $folderObject
     */
    protected $folderObject;

    /**
     * Return URL of list module.
     *
     * @var string
     */
    public $returnUrl;

    /**
     * Accumulating content
     *
     * @var string
     */
    public $content;

    /**
     * Constructor
     */
    public function __construct()
    {
        $GLOBALS['SOBE'] = $this;
        $this->init();
    }

    /**
     * Initialize
     *
     * @throws InsufficientFolderAccessPermissionsException
     * @return void
     */
    protected function init()
    {
        // Initialize GPvars:
        $this->number = GeneralUtility::_GP('number');
        $this->target = ($combinedIdentifier = GeneralUtility::_GP('target'));
        $this->returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
        // create the folder object
        if ($combinedIdentifier) {
            $this->folderObject = ResourceFactory::getInstance()->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
        }
        // Cleaning and checking target directory
        if (!$this->folderObject) {
            $title = $this->getLanguageService()->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:paramError', true);
            $message = $this->getLanguageService()->sL('LLL:EXT:lang/locallang_mod_file_list.xlf:targetNoDir', true);
            throw new \RuntimeException($title . ': ' . $message, 1294586845);
        }
        if ($this->folderObject->getStorage()->getUid() === 0) {
            throw new InsufficientFolderAccessPermissionsException('You are not allowed to access folders outside your storages', 1375889838);
        }

        // Setting the title and the icon
        /** @var IconFactory $iconFactory */
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon('apps-filetree-root', Icon::SIZE_SMALL)->render();
        $this->title = $icon . htmlspecialchars($this->folderObject->getStorage()->getName()) . ': ' . htmlspecialchars($this->folderObject->getIdentifier());
        // Setting template object
        $this->doc = GeneralUtility::makeInstance(DocumentTemplate::class);
        $this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/file_newfolder.html');
        $this->doc->JScode = $this->doc->wrapScriptTags('
			var path = "' . $this->target . '";

			function reload(a) {
				if (!changed || (changed && confirm(' . GeneralUtility::quoteJSvalue($this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.xlf:mess.redraw')) . '))) {
					var params = "&target="+encodeURIComponent(path)+"&number="+a+"&returnUrl=' . rawurlencode($this->returnUrl) . '";
					window.location.href = ' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('file_newfolder')) . '+params;
				}
			}
			function backToList() {
				top.goToModule("file_FilelistList");
			}

			var changed = 0;
		');
    }

    /**
     * Main function, rendering the main module content
     *
     * @return void
     */
    public function main()
    {
        $lang = $this->getLanguageService();
        // Start content compilation
        $this->content .= $this->doc->startPage($lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.pagetitle'));
        // Make page header:
        $pageContent = $this->doc->header($lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.pagetitle'));
        if ($this->folderObject->checkActionPermission('add')) {
            $code = '<form role="form" action="' . htmlspecialchars(BackendUtility::getModuleUrl('tce_file')) . '" method="post" name="editform">';
            // Making the selector box for the number of concurrent folder-creations
            $this->number = MathUtility::forceIntegerInRange($this->number, 1, 10);
            $code .= '
				<div class="form-group">
					<div class="form-section">
						<div class="form-group">
							<label for="number-of-new-folders">' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.number_of_folders') . ' ' . BackendUtility::cshItem('xMOD_csh_corebe', 'file_newfolder') . '</label>
							<div class="form-control-wrap">
								<div class="input-group">
									<select class="form-control form-control-adapt" name="number" id="number-of-new-folders" onchange="reload(this.options[this.selectedIndex].value);">';
            for ($a = 1; $a <= $this->folderNumber; $a++) {
                $code .= '<option value="' . $a . '"' . ($this->number == $a ? ' selected="selected"' : '') . '>' . $a . '</option>';
            }
            $code .= '
									</select>
								</div>
							</div>
						</div>
					</div>
				';
            // Making the number of new-folder boxes needed:
            for ($a = 0; $a < $this->number; $a++) {
                $code .= '
					<div class="form-section">
						<div class="form-group">
							<label for="folder_new_' . $a . '">' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.label_newfolder') . ' ' . ($a + 1) . ':</label>
							<div class="form-control-wrap">
								<input type="text" class="form-control" id="folder_new_' . $a . '" name="file[newfolder][' . $a . '][data]" onchange="changed=true;" />
								<input type="hidden" name="file[newfolder][' . $a . '][target]" value="' . htmlspecialchars($this->target) . '" />
							</div>
						</div>
					</div>';
            }
            // Making submit button for folder creation:
            $code .= '
				</div><div class="form-group">
					<input class="btn btn-default" type="submit" value="' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.submit', true) . '" />
					<input type="hidden" name="redirect" value="' . htmlspecialchars($this->returnUrl) . '" />
				</div>
				';
            // Switching form tags:
            $pageContent .= $this->doc->section($lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.newfolders'), $code);
            $pageContent .= $this->doc->sectionEnd() . '</form>';
        }

        if ($this->folderObject->getStorage()->checkUserActionPermission('add', 'File')) {
            $pageContent .= '<form action="' . htmlspecialchars(BackendUtility::getModuleUrl('online_media')) . '" method="post" name="editform2">';
            // Create a list of allowed file extensions with the readable format "youtube, vimeo" etc.
            $fileExtList = array();
            $onlineMediaFileExt = OnlineMediaHelperRegistry::getInstance()->getSupportedFileExtensions();
            foreach ($onlineMediaFileExt as $fileExt) {
                if (GeneralUtility::verifyFilenameAgainstDenyPattern($fileExt)) {
                    $fileExtList[] = '<span class="label label-success">' . strtoupper(htmlspecialchars($fileExt)) . '</span>';
                }
            }
            // Add form fields for adding media files:
            $code = '
				<div class="form-group">
					<div class="form-section">
						<div class="form-group">
							<label for="newMedia">' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:online_media.new_media.label', true) . ' ' . BackendUtility::cshItem('xMOD_csh_corebe', 'file_newMedia') . '</label>
							<div class="form-control-wrap">
								<input class="form-control" type="text" id="newMedia" name="file[newMedia][0][url]"
									placeholder="' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:online_media.new_media.placeholder', true) . '" />
								<input type="hidden" name="file[newMedia][0][target]" value="' . htmlspecialchars($this->target) . '" />
							</div>
							<div class="help-block">
								' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:online_media.new_media.allowedProviders', true) . '<br>
								' . implode(' ', $fileExtList) . '
							</div>
						</div>
					</div>
				</div>
				';
            // Submit button for creation of a new media:
            $code .= '
				<div class="form-group">
					<input class="btn btn-default" type="submit" value="' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:online_media.new_media.submit', true) . '" />
					<input type="hidden" name="redirect" value="' . htmlspecialchars($this->returnUrl) . '" />
				</div>
				';
            $pageContent .= $this->doc->section($lang->sL('LLL:EXT:lang/locallang_core.xlf:online_media.new_media', true), $code);
            $pageContent .= $this->doc->sectionEnd();
            $pageContent .= '</form>';

            $pageContent .= '<form action="' . BackendUtility::getModuleUrl('tce_file') . '" method="post" name="editform3">';
            // Create a list of allowed file extensions with the nice format "*.jpg, *.gif" etc.
            $fileExtList = array();
            $textFileExt = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'], true);
            foreach ($textFileExt as $fileExt) {
                if (GeneralUtility::verifyFilenameAgainstDenyPattern($fileExt)) {
                    $fileExtList[] = '<span class="label label-success">' . strtoupper(htmlspecialchars($fileExt)) . '</span>';
                }
            }
            // Add form fields for creation of a new, blank text file:
            $code = '
				<div class="form-group">
					<div class="form-section">
						<div class="form-group">
							<label for="newfile">' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.label_newfile', true) . ' ' . BackendUtility::cshItem('xMOD_csh_corebe', 'file_newfile') . '</label>
							<div class="form-control-wrap">
								<input class="form-control" type="text" id="newfile" name="file[newfile][0][data]" onchange="changed=true;" />
								<input type="hidden" name="file[newfile][0][target]" value="' . htmlspecialchars($this->target) . '" />
							</div>
							<div class="help-block">
								' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:cm.allowedFileExtensions', true) . '<br>
								' . implode(' ', $fileExtList) . '
							</div>
						</div>
					</div>
				</div>
				';
            // Submit button for creation of a new file:
            $code .= '
				<div class="form-group">
					<input class="btn btn-default" type="submit" value="' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.newfile_submit', true) . '" />
					<input type="hidden" name="redirect" value="' . htmlspecialchars($this->returnUrl) . '" />
				</div>
				';
            $pageContent .= $this->doc->section($lang->sL('LLL:EXT:lang/locallang_core.xlf:file_newfolder.php.newfile', true), $code);
            $pageContent .= $this->doc->sectionEnd();
            $pageContent .= '</form>';
        }

        $docHeaderButtons = array(
            'back' => ''
        );
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        // Back
        if ($this->returnUrl) {
            $docHeaderButtons['back'] = '<a href="' . htmlspecialchars(GeneralUtility::linkThisUrl($this->returnUrl)) . '" class="typo3-goBack" title="' . $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.goBack', true) . '">' . $iconFactory->getIcon('actions-view-go-back', Icon::SIZE_SMALL)->render() . '</a>';
        }
        // Add the HTML as a section:
        $markerArray = array(
            'CSH' => $docHeaderButtons['csh'],
            'FUNC_MENU' => '',
            'CONTENT' => $pageContent,
            'PATH' => $this->title
        );
        $this->content .= $this->doc->moduleBody(array(), $docHeaderButtons, $markerArray);
        $this->content .= $this->doc->endPage();
        $this->content = $this->doc->insertStylesAndJS($this->content);
    }

    /**
     * Processes the request, currently everything is handled and put together via "main()"
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->main();

        $response->getBody()->write($this->content);
        return $response;
    }

    /**
     * Outputting the accumulated content to screen
     *
     * @return void
     * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8, use the mainAction() method instead
     */
    public function printContent()
    {
        GeneralUtility::logDeprecatedFunction();
        echo $this->content;
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
