<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Table\Maintenance;

use InvalidArgumentException;
use PhpMyAdmin\Config;
use PhpMyAdmin\Controllers\Table\AbstractController;
use PhpMyAdmin\Dbal\DatabaseName;
use PhpMyAdmin\Dbal\TableName;
use PhpMyAdmin\Html\Generator;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Message;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\Table\Maintenance;
use PhpMyAdmin\Template;
use Webmozart\Assert\Assert;

use function __;
use function count;

final class ChecksumController extends AbstractController
{
    /** @var Maintenance */
    private $model;

    /** @var Config */
    private $config;

    public function __construct(
        ResponseRenderer $response,
        Template $template,
        string $db,
        string $table,
        Maintenance $model,
        Config $config
    ) {
        parent::__construct($response, $template, $db, $table);
        $this->model = $model;
        $this->config = $config;
    }

    public function __invoke(ServerRequest $request): void
    {
        $dbParam = $request->getParam('db');
        $selectedTablesParam = $request->getParsedBodyParam('selected_tbl');

        try {
            Assert::isArray($selectedTablesParam);
            Assert::notEmpty($selectedTablesParam);
            Assert::allStringNotEmpty($selectedTablesParam);
        } catch (InvalidArgumentException $exception) {
            $this->response->setRequestStatus(false);
            $this->response->addJSON('message', __('No table selected.'));

            return;
        }

        try {
            Assert::string($dbParam);
            $database = DatabaseName::fromString($dbParam);
            $selectedTables = [];
            foreach ($selectedTablesParam as $table) {
                $selectedTables[] = TableName::fromString($table);
            }
        } catch (InvalidArgumentException $exception) {
            $message = Message::error($exception->getMessage());
            $this->response->setRequestStatus(false);
            $this->response->addJSON('message', $message->getDisplay());

            return;
        }

        if ($this->config->get('DisableMultiTableMaintenance') && count($selectedTables) > 1) {
            $this->response->setRequestStatus(false);
            $this->response->addJSON('message', __('Maintenance operations on multiple tables are disabled.'));

            return;
        }

        [$rows, $query, $warnings] = $this->model->getChecksumTableRows($database, $selectedTables);

        $message = Generator::getMessage(
            __('Your SQL query has been executed successfully.'),
            $query,
            'success'
        );

        $this->render('table/maintenance/checksum', [
            'message' => $message,
            'rows' => $rows,
            'warnings' => $warnings,
        ]);
    }
}
