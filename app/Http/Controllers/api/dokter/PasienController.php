<?php

namespace App\Http\Controllers\api\dokter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PasienController extends Controller
{
    protected $payload;

    public function __construct()
    {
        $this->payload = auth()->payload();
    }

    public function index()
    {
        $kd_dokter = $this->payload->get('sub');
        $pasien    = \App\Models\RegPeriksa::with('poliklinik', 'pasien', 'penjab')
            ->where('kd_dokter', $kd_dokter)
            ->orderBy('tgl_registrasi', 'DESC')
            ->paginate(env('PER_PAGE', 20));

        return isSuccess($pasien, 'Seluruh Pasien berhasil dimuat');
    }

    public function now()
    {
        $kd_dokter = $this->payload->get('sub');

        $pasien = \App\Models\RegPeriksa::with('poliklinik', 'pasien', 'penjab')
            ->where('kd_dokter', $kd_dokter)
            ->where('tgl_registrasi', date('Y-m-d'))
            ->orderBy('jam_reg', 'DESC')
            ->paginate(env('PER_PAGE', 20));

        return isSuccess($pasien, 'Pasien hari ini berhasil dimuat');
    }

    function byDate($tahun = null, $bulan = null, $tanggal = null)
    {
        if ($tahun !== null) {
            $message = "Pasien tahun $tahun berhasil dimuat";
            $pasien  = \App\Models\RegPeriksa::with('poliklinik', 'pasien', 'penjab')
                ->where('kd_dokter', $this->payload->get('sub'))
                ->whereYear('tgl_registrasi', $tahun)
                ->orderBy('tgl_registrasi', 'DESC')
                ->orderBy('jam_reg', 'DESC')
                ->paginate(env('PER_PAGE', 20));
        }

        if ($tahun !== null && $bulan !== null) {
            $message = "Pasien bulan $bulan tahun $tahun berhasil dimuat";
            $pasien  = \App\Models\RegPeriksa::with('poliklinik', 'pasien', 'penjab')
                ->where('kd_dokter', $this->payload->get('sub'))
                ->whereYear('tgl_registrasi', $tahun)
                ->whereMonth('tgl_registrasi', $bulan)
                ->orderBy('tgl_registrasi', 'DESC')
                ->orderBy('jam_reg', 'DESC')
                ->paginate(env('PER_PAGE', 20));
        }

        if ($tahun !== null && $bulan !== null && $tanggal !== null) {
            $message  = "Pasien tanggal $tanggal bulan $bulan tahun $tahun berhasil dimuat";
            $fullDate = $tahun . '-' . $bulan . '-' . $tanggal;
            $pasien   = \App\Models\RegPeriksa::with('poliklinik', 'pasien', 'penjab')
                ->where('kd_dokter', $this->payload->get('sub'))
                ->where('tgl_registrasi', $fullDate)
                ->orderBy('tgl_registrasi', 'DESC')
                ->orderBy('jam_reg', 'DESC')
                ->paginate(env('PER_PAGE', 20));
        }

        return isSuccess($pasien, $message);
    }

    /**
     * search
     * 
     * @bodyParam keywords string
     * @bodyParam statusLanjut string
     * @bodyParam penjab string (kd_pj)
     * @bodyParam no_rawat string search example : rawat 2023/01/01/000001
     * @bodyParam rm string search example : rm 009380
     * 
     * @return json 
     **/
    public function search(Request $request)
    {
        $message = 'Data berhasil dimuat';
        $pasien  = \App\Models\RegPeriksa::with(['pasien', 'penjab', 'poliklinik'])
            ->where('kd_dokter', $this->payload->get('sub'))
            ->orderBy('tgl_registrasi', 'DESC')
            ->orderBy('jam_reg', 'DESC');

        if ($request->tgl_registrasi) {
            $start = Carbon::parse($request->tgl_registrasi['start'])->format('Y-m-d');
            $end   = Carbon::parse($request->tgl_registrasi['end'])->format('Y-m-d');

            $message .= ' dari tanggal ' . $start . ' sampai ' . $end;

            $pasien->whereBetween('tgl_registrasi', [$start, $end]);
        }

        if ($request->keywords) {
            $message .= ' dengan kata kunci ' . $request->keywords;
            $pasien->whereHas('pasien', function ($query) use ($request) {
                $query->where('nm_pasien', 'LIKE', '%' . $request->keywords . '%')
                    ->orWhere('no_rkm_medis', 'LIKE', '%' . $request->keywords . '%');
            });
        }

        if ($request->status_lanjut) {
            $message .= ' dengan status lanjut ' . $request->status_lanjut;
            $pasien->where('status_lanjut', $request->status_lanjut);
        }

        if ($request->penjab) {
            $message .= ' dengan penjab ' . $request->penjab;
            $pasien->whereHas('penjab', function ($query) use ($request) {
                $query->where('png_jawab', 'LIKE', '%' . $request->penjab . '%');
            });
        }

        $pasien = $pasien->paginate(env('PER_PAGE', 20));

        return isSuccess($pasien, $message);
    }

    /**
     * pemeriksaan
     *
     * @bodyParam no_rawat string required
     * @return json
     * 
     * @authenticated
     */
    function pemeriksaan()
    {
        // if not post return error
        if (!request()->isMethod('post')) {
            return isFail('Method not allowed');
        }

        // if no data return error
        if (!request()->has('no_rawat')) {
            return isFail('No Rawat tidak boleh kosong');
        }

        // get reg periksa data by no rawat
        $regPeriksa = \App\Models\RegPeriksa::where('no_rawat', request()->no_rawat)->first();

        if (!$regPeriksa) {
            return isFail('No Rawat tidak ditemukan');
        }

        if ($regPeriksa->status_lanjut == 'Ranap') {
            $message = 'Pemeriksaan Ranap berhasil dimuat';
            $data    = \App\Models\RegPeriksa::with([
                'poliklinik',
                'pasien',
                'penjab',
                'pemeriksaanRanap' => function ($q) {
                    $q->orderBy('tgl_perawatan', 'DESC');
                    $q->orderBy('jam_rawat', 'DESC');
                }
            ])
                ->where('no_rawat', request()->no_rawat)
                ->where('status_lanjut', 'Ranap')
                ->first();

            $data->pemeriksaan = $data->pemeriksaanRanap;
            unset($data->pemeriksaanRanap);
        } else {
            $message = 'Pemeriksaan Ralan berhasil dimuat';
            $data    = \App\Models\RegPeriksa::with([
                'poliklinik',
                'pasien',
                'penjab',
                'pemeriksaanRalan' => function ($q) {
                    $q->orderBy('tgl_perawatan', 'DESC');
                    $q->orderBy('jam_rawat', 'DESC');
                }
            ])
                ->where('no_rawat', request()->no_rawat)
                ->where('status_lanjut', 'Ralan')
                ->first();

            $data->pemeriksaan = $data->pemeriksaanRalan;
            unset($data->pemeriksaanRalan);
        }

        return isSuccess($data, $message);
    }

    function pemeriksaanChart(Request $request)
    {
        // if not post return error
        if (!$request->isMethod('post')) {
            return isFail('Method not allowed');
        }

        // if no data return error
        if (!$request->has('no_rawat')) {
            return isFail('No Rawat tidak boleh kosong');
        }

        if (!$request->has('stts_lanjut')) {
            return isFail('Status lanjut tidak boleh kosong');
        }

        if (ucfirst($request->stts_lanjut) == 'Ranap') {
            $message = 'Pemeriksaan Ranap untuk chaart berhasil dimuat';
            $data    = \App\Models\PemeriksaanRanap::select('tgl_perawatan', 'jam_rawat', 'suhu_tubuh', 'nadi', 'spo2', 'respirasi')
                ->where('no_rawat', $request->no_rawat)
                ->get();

            // $data->pemeriksaan = $data->pemeriksaanRanap;
            // unset($data->pemeriksaanRanap);
        } else {
            $message = 'Pemeriksaan Ralan untuk chaart berhasil dimuat';
            $data    = \App\Models\PemeriksaanRalan::select('tgl_perawatan', 'jam_rawat', 'suhu_tubuh', 'nadi', 'spo2', 'respirasi')
                ->where('no_rawat', $request->no_rawat)
                ->get();

            // $data->pemeriksaan = $data->pemeriksaanRalan;
            // unset($data->pemeriksaanRalan);
        }

        return isSuccess($data, $message);
    }
}