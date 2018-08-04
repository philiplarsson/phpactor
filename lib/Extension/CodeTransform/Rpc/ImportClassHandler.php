<?php

namespace Phpactor\Extension\CodeTransform\Rpc;

use Phpactor\MapResolver\Resolver;
use Phpactor\Extension\Rpc\Handler\AbstractHandler;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass;
use Phpactor\Extension\SourceCodeFilesystem\SourceCodeFilestem\Application\ClassSearch;
use Phpactor\Extension\Rpc\Response\EchoResponse;
use Phpactor\Extension\Rpc\Response\Input\ListInput;
use Phpactor\CodeTransform\Domain\SourceCode;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\NameAlreadyUsedException;
use Phpactor\Extension\Rpc\Response\UpdateFileSourceResponse;
use Phpactor\Extension\Rpc\Response\Input\TextInput;
use Phpactor\CodeTransform\Domain\Refactor\ImportClass\ClassAlreadyImportedException;
use Phpactor\CodeTransform\Domain\Exception\TransformException;

class ImportClassHandler extends AbstractHandler
{
    const PARAM_NAME = 'name';
    const PARAM_OFFSET = 'offset';
    const PARAM_SOURCE = 'source';
    const PARAM_PATH = 'path';
    const PARAM_ALIAS = 'alias';
    const PARAM_QUALIFIED_NAME = 'qualified_name';

    /**

     * @var ImportClass
     */
    private $classImport;

    /**
     * @var ClassSearch
     */
    private $classSearch;

    /**
     * @var string
     */
    private $filesystem;

    public function __construct(
        ImportClass $classImport,
        ClassSearch $classSearch,
        string $filesystem
    ) {
        $this->classImport = $classImport;
        $this->classSearch = $classSearch;
        $this->filesystem = $filesystem;
    }

    public function name(): string
    {
        return 'import_class';
    }

    public function configure(Resolver $resolver)
    {
        $resolver->setDefaults([
            self::PARAM_QUALIFIED_NAME => null,
            self::PARAM_ALIAS => null,
        ]);
        $resolver->setRequired([
            self::PARAM_NAME,
            self::PARAM_OFFSET,
            self::PARAM_SOURCE,
            self::PARAM_PATH,
        ]);
    }

    public function handle(array $arguments)
    {
        if (null === $arguments[self::PARAM_QUALIFIED_NAME]) {
            $suggestions = $this->suggestions($arguments[self::PARAM_NAME]);

            if (count($suggestions) === 0) {
                return EchoResponse::fromMessage(sprintf(
                    'No classes found with short name "%s"',
                    $arguments[self::PARAM_NAME]
                ));
            }

            if (count($suggestions) > 1) {
                $this->requireInput(
                    ListInput::fromNameLabelChoices(
                        self::PARAM_QUALIFIED_NAME,
                        'Select class:',
                        array_combine($suggestions, $suggestions)
                    )
                );
            } else {
                $arguments[self::PARAM_QUALIFIED_NAME] = reset($suggestions);
            }
        }

        if ($this->hasMissingArguments($arguments)) {
            return $this->createInputCallback($arguments);
        }

        try {
            $sourceCode = $this->classImport->importClass(
                SourceCode::fromStringAndPath(
                    $arguments[self::PARAM_SOURCE],
                    $arguments[self::PARAM_PATH]
                ),
                $arguments[self::PARAM_OFFSET],
                $arguments[self::PARAM_QUALIFIED_NAME],
                $arguments[self::PARAM_ALIAS]
            );
        } catch (NameAlreadyUsedException $e) {
            if ($e instanceof ClassAlreadyImportedException && $e->existingName() === $arguments[self::PARAM_QUALIFIED_NAME]) {
                return EchoResponse::fromMessage(sprintf(
                    'Class "%s" is already imported',
                    $arguments[self::PARAM_QUALIFIED_NAME]
                ));
            }

            $arguments[self::PARAM_ALIAS] = null;
            $this->requireInput(TextInput::fromNameLabelAndDefault(
                self::PARAM_ALIAS,
                sprintf(
                    '"%s" is already used, choose an alias: ',
                    $e->name()
                ),
                $e->name()
            ));

            return $this->createInputCallback($arguments);
        } catch (TransformException $e) {
            return EchoResponse::fromMessage($e->getMessage());
        }

        return UpdateFileSourceResponse::fromPathOldAndNewSource(
            $sourceCode->path(),
            $arguments[self::PARAM_SOURCE],
            (string) $sourceCode
        );
    }

    private function suggestions(string $name)
    {
        $suggestions = $this->classSearch->classSearch(
            $this->filesystem,
            $name
        );
        return array_map(function (array $suggestion) {
            return $suggestion['class'];
        }, $suggestions);
    }
}
