<?php

namespace App\Http\Controllers\api\dokter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PasienRalanController extends Controller
{
    protected $payload;

    public function __construct()
    {
        $this->payload = auth()->payload();
    }

    public function index()
    {
        $kd_dokter = $this->payload->get('sub');
        $pasien = \App\Models\RegPeriksa::with(['pasien', 'dokter', 'penjab', 'pemeriksaanRalan'])
            ->where('kd_dokter', $kd_dokter)
            ->where('status_lanjut', 'Ralan')
            ->orderBy('tgl_registrasi', 'DESC')
            ->orderBy('jam_reg', 'DESC')
            ->paginate(10);

        return isSuccess($pasien, 'Data berhasil dimuat');
    }
    
    public function now()
    {
        $kd_dokter = $this->payload->get('sub');
        $pasien = \App\Models\RegPeriksa::with(['pasien', 'dokter', 'penjab', 'pemeriksaanRalan'])
            ->where('kd_dokter', $kd_dokter)
            ->where('tgl_registrasi', date('Y-m-d'))
            ->where('status_lanjut', 'Ralan')
            ->orderBy('jam_reg', 'DESC')
            ->paginate(10);

        return isSuccess($pasien, 'Data berhasil dimuat');
    }

    function byDate($tahun = null, $bulan = null, $tanggal = null)
    {
        if ($tahun !== null) {
            $pasien = \App\Models\RegPeriksa::with(['pasien', 'dokter', 'penjab', 'pemeriksaanRalan'])
                ->where('kd_dokter', $this->payload->get('sub'))
                ->whereYear('tgl_registrasi', $tahun)
                ->where('status_lanjut', 'Ralan')
                ->orderBy('tgl_registrasi', 'DESC')
                ->orderBy('jam_reg', 'DESC')
                ->paginate(10);
        }

        if ($tahun !== null && $bulan !== null) {
            $pasien = \App\Models\RegPeriksa::with(['pasien', 'dokter', 'penjab', 'pemeriksaanRalan'])
                ->where('kd_dokter', $this->payload->get('sub'))
                ->whereYear('tgl_registrasi', $tahun)
                ->whereMonth('tgl_registrasi', $bulan)
                ->where('status_lanjut', 'Ralan')
                ->orderBy('tgl_registrasi', 'DESC')
                ->orderBy('jam_reg', 'DESC')
                ->paginate(10);
        }

        if ($tahun !== null && $bulan !== null && $tanggal !== null) {
            $fullDate = $tahun . '-' . $bulan . '-' . $tanggal;
            $pasien = \App\Models\RegPeriksa::with(['pasien', 'dokter', 'penjab', 'pemeriksaanRalan'])
                ->where('kd_dokter', $this->payload->get('sub'))
                ->where('tgl_registrasi', $fullDate)
                ->where('status_lanjut', 'Ralan')
                ->orderBy('tgl_registrasi', 'DESC')
                ->orderBy('jam_reg', 'DESC')
                ->paginate(10);
        }

        return isSuccess($pasien, 'Data berhasil dimuat');
    }
}
