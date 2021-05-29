<?php
declare(strict_types=1);

namespace SmolCms\Service\Core;


use InvalidArgumentException;
use ReflectionException;
use SmolCms\Data\Constant\HttpStatus;
use SmolCms\Data\Response\Response;
use SmolCms\Template\Template;

class TemplateService
{

    /**
     * TemplateService constructor.
     * @param ServiceBuilder $serviceBuilder
     */
    public function __construct(
        private ServiceBuilder $serviceBuilder
    )
    {
    }

    /**
     * @param string $templateClass
     * @param array $data
     * @return Response
     * @throws ReflectionException
     */
    public function generateResponse(string $templateClass, array $data): Response
    {
        $template = $this->serviceBuilder->getService($templateClass);
        if (!($template instanceof Template)) {
            throw new InvalidArgumentException(
                "$templateClass is not a template class! Make sure it implements the Template interface."
            );
        }
        return new Response(HttpStatus::OK, $template->render($data));
    }
}