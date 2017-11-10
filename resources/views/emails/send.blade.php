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
@if(count($lineas)>0)
<h4 style="text-align:center">Los siguientes archivos contienen registros que no se cargarón, (Se adjuntan en el correo): </h4>
@foreach($lineas as $l)
    <p>{{$l}}</p>
@endforeach
@endif

@if(count($lineas2)>0)
<h4 style="text-align:center">Se cargaron satisfactoriamente los siguientes archivos al servidor ftp: </h4>
@foreach($lineas2 as $l)
    <p>{{$l}}</p>
@endforeach
@endif

@if(count($lineas3)>0)
    <h4 style="text-align:center">Los siguientes archivos no se pudieron cargar al servidor ftp: </h4>
    @foreach($lineas3 as $l)
        <p>{{$l}}</p>
    @endforeach
@endif
</body>
</html>