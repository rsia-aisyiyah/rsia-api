<?php

namespace App\Http\Controllers\api\dokter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KunjunganController extends Controller
{
    protected $payload;

    public function __construct()
    {
        $this->payload = auth()->payload();
    }

    public function index()
    {
        $kd_dokter = $this->payload->get('sub');
        $kunjungan = \App\Models\RegPeriksa::where('kd_dokter', $kd_dokter)
            ->orderBy('tgl_registrasi', 'desc')
            ->orderBy('jam_reg', 'desc')
            ->paginate(env('PER_PAGE', 20));

        return isSuccess($kunjungan, 'Data berhasil dimuat');
    }

    public function now()
    {
        $kd_dokter = $this->payload->get('sub');
        $kunjungan = \App\Models\RegPeriksa::where('kd_dokter', $kd_dokter)
            ->where('tgl_registrasi', date('Y-m-d'))
            ->paginate(env('PER_PAGE', 20));

        return isSuccess($kunjungan, 'Data berhasil dimuat');
    }

    private function getTotal($source)
    {   
        $dataMap = ["UMUM", "BPJS", "TOTAL"];
        $data = $source->pluck('penjab')->countBy(
            function ($item, $key) {
                return str_contains($item['png_jawab'], "BPJS") ? 'BPJS' : $item['png_jawab'];
            }
        );

        foreach ($dataMap as $key => $value) {
            if (!isset($data[$value])) {
                $data[$value] = 0;
            }

            if ($value == "TOTAL") {
                $data[$value] = $data['UMUM'] + $data['BPJS'];
            }
        }

        return $data;
    }

    function rekap(Request $request)
    {

        if (!$request->isMethod('post')) {
            return isFail('Method not allowed');
        }

        $pasien = \App\Models\RegPeriksa::with(['pasien', 'penjab'])
            ->where('kd_dokter', $this->payload->get('sub'))
            ->orderBy('tgl_registrasi', 'DESC')
            ->orderBy('jam_reg', 'DESC');

        $operasi = \App\Models\RegPeriksa::with(['pasien', 'penjab'])
            ->where('kd_dokter', $this->payload->get('sub'))
            ->whereHas('operasi')
            ->orderBy('tgl_registrasi', 'DESC')
            ->orderBy('jam_reg', 'DESC');

        $start = date('Y-m-01');
        $end   = date('Y-m-t');

        if ($request->tgl_registrasi) {
            $start = \Illuminate\Support\Carbon::parse($request->tgl_registrasi['start'])->format('Y-m-d');
            $end   = \Illuminate\Support\Carbon::parse($request->tgl_registrasi['end'])->format('Y-m-d');

        }

        $pasien->whereBetween('tgl_registrasi', [$start, $end]);
        $operasi->whereBetween('tgl_registrasi', [$start, $end]);

        $data = $pasien->get()->groupBy('status_lanjut')->map(function ($item, $key) {
            return $this->getTotal(collect($item));
        });
        $data['Operasi'] = $this->getTotal($operasi->get());

        return isSuccess($data, 'Data berhasil dimuat');
    }

    function byDate($tahun = null, $bulan = null, $tanggal = null)
    {
        if ($tahun !== null) {
            $kunjungan = \App\Models\RegPeriksa::where('kd_dokter', $this->payload->get('sub'))
                ->whereYear('tgl_registrasi', $tahun)
                ->orderBy('tgl_registrasi', 'desc')
                ->orderBy('jam_reg', 'desc')
                ->paginate(env('PER_PAGE', 20));
        }

        if ($tahun !== null && $bulan !== null) {
            $kunjungan = \App\Models\RegPeriksa::where('kd_dokter', $this->payload->get('sub'))
                ->whereYear('tgl_registrasi', $tahun)
                ->whereMonth('tgl_registrasi', $bulan)
                ->orderBy('tgl_registrasi', 'desc')
                ->orderBy('jam_reg', 'desc')
                ->paginate(env('PER_PAGE', 20));
        }

        if ($tahun !== null && $bulan !== null && $tanggal !== null) {
            $fullDate  = $tahun . '-' . $bulan . '-' . $tanggal;
            $kunjungan = \App\Models\RegPeriksa::where('kd_dokter', $this->payload->get('sub'))
                ->where('tgl_registrasi', $fullDate)
                ->orderBy('tgl_registrasi', 'desc')
                ->orderBy('jam_reg', 'desc')
                ->paginate(env('PER_PAGE', 20));
        }

        return isSuccess($kunjungan, 'Data berhasil dimuat');
    }
}