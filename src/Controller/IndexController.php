<?php declare(strict_types=1);
namespace CmapImport\Controller;

use CmapImport\Form\ImportForm;
use CmapImport\Job;
use DateTime;
use Laminas\Config\Reader\Xml;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{
    public function __construct()
    {
    }

    public function importAction()
    {
        $form = $this->getForm(ImportForm::class);

        $request = $this->getRequest();

        if ($request->isPost()) {
            $data = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            if ($data['itemSet'] && $data['file']) {
                $timestamp = new DateTime();
                $timestamp = (int) $timestamp->format('U');
                $args = [
                    'itemSet' => $data['itemSet'],
                    'file' => $data['file']['tmp_name'],
                    'version' => 1,
                    'timestamp' => $timestamp,
                    'data' => [],
                 ];

                $response = $this->getFileContent($data['file']['tmp_name']);
                if ($response) {
                    $import = $this->api()->create('cmap_imports', [
                        'o-module-cmap_import:version' => $args['version'],
                        'o-module-cmap_import:name' => $data['file']['name'],
                        'o-module-cmap_import:url' => $data['file']['tmp_name'],
                    ])->getContent();
                    $args['import'] = $import->id();
                    $args['data'] = $response;
                    $job = $this->jobDispatcher()->dispatch(Job\Import::class, $args);
                    $this->api()->update('cmap_imports', $import->id(), [
                        'o:job' => ['o:id' => $job->getId()],
                    ]);
                    $message = new Message(
                        'Importing from Cmap. %s', // @translate
                        sprintf(
                            '<a href="%s">%s</a>',
                            htmlspecialchars($this->url()->fromRoute(null, [], true)),
                            $this->translate('Import another?')
                        ));
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toRoute('admin/Cmap-import/default', ['action' => 'browse']);
                } else {
                    $this->messenger()->addError(sprintf(
                        'Error when requesting Cmap library: %s%s', // @translate
                        $response->getReasonPhrase(),
                        'message'
                    ));
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('id');
        $response = $this->api()->search('cmap_imports', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('imports', $response->getContent());
        return $view;
    }

    public function undoConfirmAction()
    {
        $import = $this->api()
            ->read('cmap_imports', $this->params('import-id'))->getContent();
        $form = $this->getForm(ConfirmForm::class);
        $form->setAttribute('action', $import->url('undo'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('cmap-import/index/undo-confirm');
        $view->setVariable('import', $import);
        $view->setVariable('form', $form);
        return $view;
    }

    public function undoAction()
    {
        if ($this->getRequest()->isPost()) {
            $import = $this->api()
                ->read('cmap_imports', $this->params('import-id'))->getContent();
            if (in_array($import->job()->status(), ['completed', 'stopped', 'error'])) {
                $form = $this->getForm(ConfirmForm::class);
                $form->setData($this->getRequest()->getPost());
                if ($form->isValid()) {
                    $args = ['import' => $import->id()];
                    $job = $this->jobDispatcher()->dispatch(Job\UndoImport::class, $args);
                    $this->api()->update('cmap_imports', $import->id(), [
                        'o-module-cmap_import:undo_job' => ['o:id' => $job->getId()],
                    ]);
                    $this->messenger()->addSuccess('Undoing Cmap import'); // @translate
                } else {
                    $this->messenger()->addFormErrors($form);
                }
            }
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /**
     * Get the content of file
     *
     * @param string $file
     * @return Response
     */
    protected function getFileContent($file)
    {
        $xml = new Xml();
        return $xml->fromFile($file);
    }
}
