<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Process files</title>
    <!-- FIN DE METADATOS -->

</head>
<body>
<!--CONTENEDOR PADRE-->
<h4 style="text-align:center">Los siguientes archivos fueron enviados</h4>
@foreach($lineas as $l)
    <p>{{$l}}</p>
@endforeach
</body>
</html>