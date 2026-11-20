@extends('layouts.app')

@section('title', 'Ujian Selesai')
@section('heading', 'Ujian Selesai')

@section('content')
    <x-exam-completed :participant="$student->full_name" :exam="$attempt->assignment->assessmentSubject->subject->name"
        :dashboard-url="route('student.exams.index')" />
@endsection
