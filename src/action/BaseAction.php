<?php

declare(strict_types=1);

namespace joole\components\action;

use Closure;
use joole\framework\controller\ControllerInterface;
use joole\framework\exception\component\ComponentException;
use joole\framework\http\request\BaseRequest;
use joole\framework\http\request\Mutations;
use joole\framework\http\response\BaseResponse;
use joole\framework\routing\ActionInterface;
use joole\framework\validator\http\RequestValidatorInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;

class BaseAction implements ActionInterface
{

    /** @var string An action name */
    public readonly string $name;
    /** @var RequestValidatorInterface[] Validators for action */
    private array $validators = [];
    private array|string|Closure $executionPath;

    public function __construct(string $name, array|callable|string|Closure $executionPath)
    {
        $this->name = $name;
        $this->executionPath = $executionPath;
    }

    public function withValidators(string|RequestValidatorInterface ...$validators): static
    {
        foreach ($validators as $validator) {
            if (is_string($validator)) {
                if (!is_subclass_of($validator, RequestValidatorInterface::class)) {
                    throw new ComponentException(
                        'Validator must be instanceof '
                        . RequestValidatorInterface::class . '! '
                        . $validator . ' given.'
                    );
                }

                $this->validators[] = new $validator;

                continue;
            }

            if (array_search($validator, $this->validators) !== -1) {
                $this->validators[] = $validator;
            }
        }

        return $this;
    }

    public function getValidators(): array
    {
        return $this->validators;
    }

    // TODO: implement middlewares
    public function withMiddlewares(): static
    {
        return $this;
    }

    public function execute(array $params): BaseResponse
    {
        $this->validateValues($params);

        $callback = $this->executionPath;
        $request = request();

        foreach ($params as $paramName => $value) {
            $request->mutate(Mutations::GET_MUTATION, $paramName, $value);
        }

        if ($callback instanceof Closure) {
            return $this->closure($callback, $params);
        } else {
            // If runtime using "controller@method" pattern.
            if (is_string($callback)) {
                // [Controller::class, "action"]
                $callback = explode('@', $callback);
            }

            // Checking for the existence of the controller element
            if (!isset($callback[0])) {
                throw new ComponentException('Controller not included. Please, use "[\ControllerClass::class, "method"]" array!');
            }

            //Checking for the existence of the method element
            if (!isset($callback[1])) {
                throw new ComponentException('Method not included. Please, use "[\ControllerClass::class, "method"]" array!');
            }

            return $this->controller($callback[0], $callback[1], $params);
        }
    }

    public function validateValues(array $data): bool
    {
        $validationFailed = false;

        foreach ($this->validators as $validator) {
            $validationFailed = !$validationFailed && !$validator->validate($data);
        }

        return !$validationFailed;
    }

    /**
     * Returns the parameters for calling the function in order,
     * while using the parameters from the action and,
     * if they are not found, sets the default values.
     *
     * @param ReflectionFunctionAbstract $function Reflected method of anonymous function.
     * @param array $actionParams Params from action.
     * @return array Function params.
     *
     * @throws ComponentException
     */
    private static function getConstructorParams(ReflectionFunctionAbstract $function, array $actionParams = [])
    {
        // Params of function.
        $params = $function->getParameters();
        // Array of parameters in order.
        $functionCallParams = [];

        foreach ($params as $param) {
            $name = $param->getName();// Param name.
            $type = $param->getType();// Type class (reflected).
            $class = $type->getName();// Class or name of type.
            $paramPos = $param->getPosition();// Param position

            // Allows Request class only.
            if (is_subclass_of($class, BaseRequest::class)) {
                // Writing request.
                $functionCallParams[$paramPos] = request();

                continue;
            }

            // If it's not bound param
            if (!isset($actionParams[$name])) {
                // If param has default value
                if ($param->isDefaultValueAvailable()) {
                    // Binding default value
                    $functionCallParams[$paramPos] = $param->getDefaultValue();
                } else {
                    throw new ComponentException('Param $' . $name . ' not set for function');
                }
            } else {
                // If it's bound param => setting it.
                $functionCallParams[$paramPos] = $actionParams[$name];
            }
        }

        return $functionCallParams;
    }

    /**
     * This method calls Closure with params from action.
     *
     * If action not provides value for method => using default.
     *
     * @param Closure $closure Closure for action.
     * @param array $actionParams Params from action.
     *
     * @return BaseResponse
     * @throws \ReflectionException
     *
     * @throws ComponentException
     */
    public function closure(Closure $closure, array $actionParams = []): BaseResponse
    {
        // Reflected function using in function for constructor param parsing.
        $reflectedClosure = new ReflectionFunction($closure);
        // Built params from method params.
        $params = self::getConstructorParams($reflectedClosure, $actionParams);

        return call_user_func_array($closure, $params);
    }

    /**
     * This method calls controller method with params from action.
     *
     * If action not provides value for method => using default.
     *
     * @param string $controller Controller as string class.
     * @param string $method Method as string
     * @param array $actionParams Params from action.
     *
     * @return BaseResponse
     * @throws \ReflectionException
     *
     * @throws ComponentException
     */
    public function controller(string $controller, string $method, array $actionParams = []): BaseResponse
    {
        // If class not exist.
        if (!class_exists($controller)) {
            throw new ComponentException('Controller class ' . $controller . ' doesn\'t exist');
        }

        // Controller must be instanced of ControllerInterface!
        if (!is_subclass_of($controller, ControllerInterface::class)) {
            throw new ComponentException('Controller ' . $controller . ' must be instance of ' . ControllerInterface::class);
        }

        /**
         * TODO: building controller with using containers and them objects.
         *
         * @var ControllerInterface $controller
         */
        $controller = new $controller($this);
        // Reflected method using in function for constructor param parsing.
        $reflectedMethod = (new ReflectionClass($controller))->getMethod($method);
        // Built params from method params.
        $params = self::getConstructorParams($reflectedMethod, $actionParams);

        // "before" call
        $controller->beforeAction($method);
        $result = call_user_func_array([$controller, $method], $params);
        // "after" call
        $controller->afterAction($method);

        // Calling.
        return $result;
    }

    /**
     * Returns errors of all validators.
     *
     * @return array
     */
    public function getValidatorErrors():array{
        $errors = [];

        /** @var \joole\framework\validator\http\RequestValidator $validator */
        foreach ($this->validators as $validator){
            $errors = [...$errors, ...$validator->getErrors()];
        }

        return $errors;
    }
}