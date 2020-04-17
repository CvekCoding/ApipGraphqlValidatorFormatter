<?php
/*
 * This file is part of the Aqua Delivery package.
 *
 * (c) Sergey Logachev <svlogachev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Cvek\ApipGraphqlValidatorFormatter;

use ApiPlatform\Core\GraphQl\Resolver\Stage\ValidateStageInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Core\Validator\ValidatorInterface;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\Serializer\SerializerInterface;

final class ValidateStage implements ValidateStageInterface
{
    private ValidateStageInterface $validateStage;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private ValidatorInterface $validator;
    private SerializerInterface $serializer;

    public function __construct(ValidateStageInterface $validateStage,
                                ResourceMetadataFactoryInterface $resourceMetadataFactory,
                                ValidatorInterface $validator,
                                SerializerInterface $serializer)
    {
        $this->validateStage = $validateStage;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->validator = $validator;
        $this->serializer = $serializer;
    }

    public function __invoke($object, string $resourceClass, string $operationName, array $context): void
    {
        try {
            ($this->validateStage)($object, $resourceClass, $operationName, $context);
        } catch (Error $error) {
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
            $validationGroups = $resourceMetadata->getGraphqlAttribute($operationName, 'validation_groups', null, true);
            try {
                $this->validator->validate($object, ['groups' => $validationGroups]);
            } catch (ValidationException $e) {
                /** @var ResolveInfo $info */
                $info = $context['info'];

                throw Error::createLocatedError($this->serializer->serialize($e->getConstraintViolationList(),'jsonproblem'), $info->fieldNodes, $info->path);
            } catch (\Exception $e) {
                throw $error;
            }
        }
    }
}
