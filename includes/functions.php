<?php
/**
 * Formatea una fecha en formato legible
 * @param string $fecha Fecha en formato YYYY-MM-DD
 * @return string Fecha formateada en español
 */
function fechaFormateada($fecha) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    if (empty($fecha)) {
        return '';
    }
    
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('m', $timestamp)];
    $año = date('Y', $timestamp);
    
    return "$dia de $mes de $año";
}

/**
 * Formatea una fecha y hora en formato legible
 * @param string $fechaHora Fecha y hora en formato YYYY-MM-DD HH:MM:SS
 * @return string Fecha y hora formateada en español
 */
function fechaHoraFormateada($fechaHora) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    if (empty($fechaHora)) {
        return '';
    }
    
    $timestamp = strtotime($fechaHora);
    $dia = date('d', $timestamp);
    $mes = $meses[(int)date('m', $timestamp)];
    $año = date('Y', $timestamp);
    $hora = date('H:i', $timestamp);
    
    return "$dia de $mes de $año a las $hora";
}
