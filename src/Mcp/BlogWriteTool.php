<?php declare(strict_types=1);

namespace Inocean\Blog\Mcp;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Uuid\Uuid;

abstract class BlogWriteTool extends McpToolResponse
{
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    protected function write(string $payload, bool $dryRun, array $fields): string
    {
        $data = $this->decodeJsonOrError($payload, 'payload');
        if (\is_string($data)) {
            return $data;
        }

        $id = $data['id'] ?? null;
        if ($id !== null && (!\is_string($id) || !Uuid::isValid($id))) {
            return $this->error('id must be a Shopware UUID.', McpToolError::INVALID_ARGUMENTS);
        }

        $context = $this->contextProvider->getContext();
        $entity = $this->repository->getDefinition()->getEntityName();
        $action = $id === null ? 'create' : 'update';
        if ($error = $this->requirePrivilege($context, "$entity:$action")) {
            return $error;
        }

        unset($data['id']);
        if ($data === [] || array_diff(array_keys($data), $fields) !== []) {
            return $this->error('Provide a single JSON object with supported fields.', McpToolError::INVALID_ARGUMENTS);
        }
        foreach ($data as $value) {
            if (\is_array($value)) {
                return $this->error('Use scalar field values. Set sw-language-id to edit a translation.', McpToolError::INVALID_ARGUMENTS);
            }
        }
        $data['id'] = $id ?? Uuid::randomHex();
        $operation = function () use ($action, $data, $context, $dryRun): string {
            $this->repository->{$action}([$data], $context);

            return $this->success(['id' => $data['id'], 'operation' => $action], ['dryRun' => $dryRun]);
        };

        if ($dryRun) {
            $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
            try {
                return $this->executeWithDryRun($this->connection, $context, $operation);
            } finally {
                $context->removeState(EntityIndexerRegistry::DISABLE_INDEXING);
            }
        }

        try {
            return $operation();
        } catch (\Shopware\Core\Framework\ShopwareHttpException $exception) {
            return $this->error($exception->getMessage(), McpToolError::INVALID_ARGUMENTS);
        }
    }
}
