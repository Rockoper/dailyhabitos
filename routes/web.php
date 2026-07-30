<?php

use App\Models\Habit;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'pages.dashboard')->name('dashboard');

    Route::view('/habitos/hoy', 'pages.habits.today')->name('habits.today');
    Route::view('/habitos', 'pages.habits.index')->name('habits.index');
    Route::view('/habitos/crear', 'pages.habits.create')->name('habits.create');
    Route::get('/habitos/{habit}/editar', fn (Habit $habit) => view('pages.habits.edit', ['habit' => $habit]))->name('habits.edit');
    Route::get('/habitos/{habit}', fn (Habit $habit) => view('pages.habits.show', ['habit' => $habit]))->name('habits.show');

    Route::view('/calendario', 'pages.coming-soon', ['title' => 'Calendario anual'])->name('calendar.index');
    Route::view('/estadisticas', 'pages.coming-soon', ['title' => 'Estadísticas'])->name('stats.index');
    Route::view('/objetivos', 'pages.coming-soon', ['title' => 'Objetivos'])->name('goals.index');
    Route::view('/reflexion', 'pages.coming-soon', ['title' => 'Reflexión diaria'])->name('reflections.index');
    Route::view('/historial', 'pages.coming-soon', ['title' => 'Historial'])->name('history.index');
    Route::view('/perfil', 'pages.coming-soon', ['title' => 'Perfil y configuración'])->name('profile.edit');
});
