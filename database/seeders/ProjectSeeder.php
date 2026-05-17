<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('projects')->insert([
            ['code' => 'FREEWIFI','name' => 'Free WiFi for All','report_type' => 'freewifi','marker_color' => '#0ea5e9','marker_shape' => 'circle','marker_icon' => 'wifi','logo_filename' => 'freewifi.png','description' => 'Free WiFi for All program','is_active' => true],
            ['code' => 'PNPKI','name' => 'Philippine National PKI','report_type' => 'milestone','marker_color' => '#7c3aed','marker_shape' => 'diamond','marker_icon' => 'certificate','logo_filename' => 'pnpki.png','description' => 'Philippine National Public Key Infrastructure','is_active' => true],
            ['code' => 'ILCDB','name' => 'Internet and Libraries Connectivity for Development of Barangays','report_type' => 'milestone','marker_color' => '#f97316','marker_shape' => 'hexagon','marker_icon' => 'library','logo_filename' => 'ilcdb.png','description' => 'Internet and Libraries Connectivity for Development of Barangays','is_active' => true],
            ['code' => 'IIDB','name' => 'Integrated ICT for Development Bureau','report_type' => 'milestone','marker_color' => '#10b981','marker_shape' => 'circle','marker_icon' => 'building-bank','logo_filename' => 'iidb.png','description' => 'Integrated ICT for Development Bureau','is_active' => true],
            ['code' => 'CYBER','name' => 'Cybersecurity','report_type' => 'milestone','marker_color' => '#ef4444','marker_shape' => 'star','marker_icon' => 'shield-lock','logo_filename' => 'cybersecurity.png','description' => 'Cybersecurity program','is_active' => true],
            ['code' => 'ELGU','name' => 'eLGU','report_type' => 'milestone','marker_color' => '#d97706','marker_shape' => 'square','marker_icon' => 'building-community','logo_filename' => 'elgu.png','description' => 'eLGU program','is_active' => true],
            ['code' => 'EGOV','name' => 'eGov PH','report_type' => 'milestone','marker_color' => '#06b6d4','marker_shape' => 'circle','marker_icon' => 'id','logo_filename' => 'egov.png','description' => 'eGov PH program','is_active' => true],
            ['code' => 'GOVNET','name' => 'GovNet','report_type' => 'milestone','marker_color' => '#6366f1','marker_shape' => 'diamond','marker_icon' => 'network','logo_filename' => 'govnet.png','description' => 'GovNet program','is_active' => true],
            ['code' => 'GECS','name' => 'GECS','report_type' => 'milestone','marker_color' => '#ec4899','marker_shape' => 'hexagon','marker_icon' => 'school','logo_filename' => 'gecs.png','description' => 'GECS program','is_active' => true],
        ]);
    }
}
