<?php

namespace App\Http\Controllers;

use App\Models\ReportComment;
use App\Models\ReportVote;
use App\Models\UserReports;
use App\Services\ImageService;
use Illuminate\Http\Request;

class UserReportsController extends Controller
{

    protected $imageService;

    public function __construct(ImageService $imageService) {
        $this->imageService = $imageService;
    }

    public function reportsList() {
        $reports = UserReports::with('user')
        ->withCount(['votes as votes_conforme' => function ($query) {
                $query->where('vote', 'conforme');
            }, 'votes as votes_no_conforme' => function ($query) {
                $query->where('vote', 'no_conforme');
             },
            'comments as comments_count'])->orderBy('created_at', 'desc')->paginate(10);

        $filteredReports = collect($reports->items())->map(function ($report) {
            return [
                'id' => $report->id,
                'titulo' => $report->titulo,
                'estado' => $report->estado ?? null,
                'fecha_hora_report' => $report->fecha_hora_report,
                'direccion' => $report->direccion,
                'descripcion' => $report->descripcion,
                'latitude' => $report->latitude,
                'longitude' => $report->longitude,
                'user' => [
                    'id' => $report->user->id ?? null,
                    'name' => $report->user->name ?? null,
                    'image_profile' => $report->user && $report->user->image_profile
                        ? asset('storage/' . $report->user->image_profile)
                        : null,
                ],

                'image' => $report->image ? asset($report->image) : null,
                'video' => $report->video ? asset($report->video) : null,

                'votes_conforme' => $report->votes_conforme,
                'votes_no_conforme' => $report->votes_no_conforme,
                'comments_count' => $report->comments_count,
            ];
        });

        return response()->json([
            'status' => 200,
            'message' => 'Lista de reportes',
            'data' =>  $filteredReports,
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'next_page_url' => $reports->nextPageUrl(),
                'has_more' => $reports->hasMorePages(),
            ]
        ], 200);
    }

    public function oneReportId($id) {
        $report = UserReports::with(['user', 'comments.user'])
            ->withCount([
                'votes as votes_conforme' => function ($query) {
                    $query->where('vote', 'conforme');
                },
                'votes as votes_no_conforme' => function ($query) {
                    $query->where('vote', 'no_conforme');
                },
                'comments as comments_count'
            ])->find($id);
    
        if (!$report) {
            return response()->json([
                'status' => 404,
                'message' => 'Reporte no encontrado',
            ], 404);
        }
    
        $reportData = [
            'id' => $report->id,
            'titulo' => $report->titulo,
            'estado' => $report->estado ?? null,
            'fecha_hora_report' => $report->fecha_hora_report,
            'direccion' => $report->direccion,
            'descripcion' => $report->descripcion,
            'latitude' => $report->latitude,
            'longitude' => $report->longitude,
            'user' => [
                'id' => $report->user->id ?? null,
                'name' => $report->user->name ?? null,
                'image_profile' => $report->user && $report->user->image_profile
                    ? asset('storage/' . $report->user->image_profile)
                    : null,
            ],
            'image' => $report->image ? asset($report->image) : null,
            'video' => $report->video ? asset($report->video) : null,
            'votes_conforme' => $report->votes_conforme,
            'votes_no_conforme' => $report->votes_no_conforme,
            'comments_count' => $report->comments_count,
            'comments' => $report->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user' => [
                        'id' => $comment->user->id ?? null,
                        'name' => $comment->user->name ?? null,
                        'image_profile' => $comment->user && $comment->user->image_profile
                            ? asset('storage/' . $comment->user->image_profile)
                            : null,
                    ],
                    'comentario' => $comment->comentario,
                    'created_at' => $comment->created_at,
                ];
            }),
        ];
    
        return response()->json([
            'status' => 200,
            'message' => 'Detalle del reporte',
            'data' => $reportData,
        ], 200);
    }

    public function userReportCreate(Request $request) {

        $validation = $this->imageService->validateFile($request, [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'direccion' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'user_id' => 'required|exists:users,id',

            'image' => 'mimes:jpeg,png,jpg,gif|max:5120',
            'video' => 'mimes:mp4,mov,avi,flv|max:20480',
        ]);

        if (!$validation['status']) {
            return response()->json([
                'status' => false,
                'errors' => $validation['errors'],
            ], 422);
        }

        $imagePath = null;
        $videoPath = null;

        // Subir imagen si existe
        if ($request->hasFile('image')) {
            $imagePath = $this->imageService->uploadFile($request->file('image'), 'images');
        }

        // Subir video si existe
        if ($request->hasFile('video')) {
            $videoPath = $this->imageService->uploadFile($request->file('video'), 'videos');
        }

        // Crear reporte
        $report = UserReports::create([
            'titulo' => $request->input('titulo'),
            'descripcion' => $request->input('descripcion'),
            'latitude' => $request->input('latitude'),
            'estado' => $request->input('estado', collect(['nuevo', 'verificado', 'urgente'])->random()),
            'direccion' => $request->input('direccion'),
            'longitude' => $request->input('longitude'),
            'image' => $imagePath ? 'storage/' . $imagePath : null,
            'video' => $videoPath ? 'storage/' . $videoPath : null,
            'fecha_hora_report' => $request->input('fecha_hora_report') ?? now()->toDateTimeString(),
            'user_id' => $request->user()->id
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Reporte subido exitosamente',
            'report' => $report,
            'image_url' => $imagePath ? asset('storage/' . $imagePath) : null,
            'video_url' => $videoPath ? asset('storage/' . $videoPath) : null,
        ], 201);
    }

    public function userReportDelete(Request $request, $id) {

        $report = UserReports::find($id);

        if (!$report) {
            return response()->json([
                'status' => 404,
                'message' => 'Reporte no encontrado',
            ], 404);
        }

        // Eliminar imagen y video si existen
        if ($report->image) {
            $this->imageService->deleteFile($report->image);
        }
        if ($report->video) {
            $this->imageService->deleteFile($report->video);
        }

        // Eliminar reporte
        $report->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Reporte eliminado exitosamente',
        ], 200);
    }

    public function userReportUpdate(Request $request, $id) {
        $report = UserReports::find($id);

        if (!$report) {
            return response()->json([
                'status' => 404,
                'message' => 'Reporte no encontrado',
            ], 404);
        }

        // Validar campos
        $validation = $this->imageService->validateFile($request, [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'user_id' => 'required|exists:users,id',

            'image' => 'mimes:jpeg,png,jpg,gif|max:5120',
            'video' => 'mimes:mp4,mov,avi,flv|max:20480',
        ]);

        if (!$validation['status']) {
            return response()->json([
                'status' => false,
                'errors' => $validation['errors'],
            ], 422);
        }

        // Subir imagen si existe
        if ($request->hasFile('image')) {
            if ($report->image) {
                $this->imageService->deleteFile($report->image);
            }
            $report->image = $this->imageService->uploadFile($request->file('image'), 'images');
        }

        // Subir video si existe
        if ($request->hasFile('video')) {
            if ($report->video) {
                $this->imageService->deleteFile($report->video);
            }
            $report->video = $this->imageService->uploadFile($request->file('video'), 'videos');
        }

        // Actualizar reporte
        $report->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Reporte actualizado exitosamente',
            'report' => $report,
        ], 200);
    }

}
