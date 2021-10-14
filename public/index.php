<?php

use Alfa\Database;
use Alfa\Materia;
use Alfa\Query;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Symfony\Component\VarDumper\VarDumper;
use Slim\Psr7\Response as Psr7Response;

require_once './../vendor/autoload.php';

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$database = new Database(
    getenv('DATABASE_HOST'),
    getenv('DATABASE_NAME'),
    getenv('DATABASE_USER'),
    getenv('DATABASE_PASS')
);

$authMiddleware =  function (Request $request, RequestHandlerInterface $handler) {
    if (!isset($request->getHeaders()['Authorization'][0])) {
        $response = new Psr7Response();
        $response->getBody()->write(
            json_encode(['error' => 'Token não informado'])
        );
        return $response->withHeader('Content-Type', 'application/json')
                 ->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
    }
    return $handler->handle($request);
};

$logMiddleware = function (Request $request, RequestHandlerInterface $handler) {
    $inicio = microtime(true);
    $response = $handler->handle($request);    
    $fim = microtime(true);
    file_put_contents("../log/access_log",
        sprintf("%s [%s] %s %s %ss\n",
            date("d/m/Y H:i:s"),
            $request->getMethod(),
            $request->getUri(),
            $response->getStatusCode(),
            round($fim-$inicio, 2)            
        ),
        FILE_APPEND
    );
    return $response;
};

$query = new Query($database);

$app->post('/materias', function (Request $request, Response $response) use ($query) {
    
    $materiaRequest = json_decode($request->getBody()->getContents());
    
    $materia = new Materia;
    $materia->nome = $materiaRequest->nome;
    $materia->dia = $materiaRequest->dia;
    $materia->horario = $materiaRequest->horario;

    $id = $query->insert($materia);

    $newMateria = $query->find($id, Materia::class);
    
    $response->getBody()->write(json_encode($newMateria));
    return $response
             ->withHeader('Content-Type', 'application/json')
             ->withStatus(201);

})->add($logMiddleware);

$app->get('/materias/{id}', function (Request $request, Response $response, $args) use($query) {
    
    $id = $args['id'];
    $materia = $query->find($id, Materia::class);
    if (is_null($materia)) {
        return $response->withStatus(404);
    }

    $response->getBody()->write(json_encode($materia));
    return $response
              ->withHeader('Content-Type', 'application/json');
});

$app->get('/materias', function (Request $request, Response $response) use ($query){
    $response->getBody()->write(json_encode($query->findAll(Materia::class)));
    return $response->withHeader('Content-Type', 'application/json')
             ->withStatus(200);
})->add($logMiddleware)
  ->add($authMiddleware);

$app->put('/materias/{id}', function (Request $request, Response $response, array $args) use ($query) {
    
    $id = $args['id'];
    
    $materia = $query->find($id, Materia::class);
    if (is_null($materia)) {
        return $response->withStatus(404);
    }

    $materiaRequest = json_decode($request->getBody()->getContents());
    
    $materia->nome = $materiaRequest->nome;
    $materia->dia = $materiaRequest->dia;
    $materia->horario = $materiaRequest->horario;

    unset($materia->dataatualizacao);

    $query->update($materia);

    $response->getBody()->write(json_encode($query->find($id, Materia::class)));
    return $response->withHeader('Content-Type', 'application/json')
                    ->withStatus(200); 

});

$app->delete('/materias/{id}', function (Request $request, Response $response, $args) use ($query) {
    $materia = $query->find($args['id'], Materia::class);
    if (is_null($materia)) {
        return $response->withStatus(404);
    }
    $query->delete($materia);
    return $response->withStatus(204);
});


/*$app->get('/', function(Request $request, Response $response){
    $response->getBody()->write("Primeira rota");
    return $response;
});

$app->get('/produtos/{idproduto}', function(Request $request, Response $response, $args){
    $response->getBody()->write("Produto ".$args['idproduto']);
    return $response;
});

$app->post('/produtos', function(Request $request, Response $response) {
    $produto = $request->getBody()->getContents();
    $response->getBody()->write($produto);    
    return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
});

$app->put('/produtos/{id}', function (Request $request, Response $response, array $args) {
    $produto = $request->getBody()->getContents();
    $response->getBody()->write($produto);
    return $response
            ->withHeader('Content-Type', 'application/json');
});

$app->delete('/produtos/{id}', function (Request $request, Response $response, $args) {
    return $response
                ->withStatus(204);
});*/

$app->run();