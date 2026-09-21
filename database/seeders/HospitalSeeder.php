<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Fictional hospital reference data — departments, doctors, services.
 *
 * This is structured data the agent's tools query directly (find_doctors,
 * get_department_info, list_services, get_visiting_hours). It is never
 * embedded or chunked; it lives in ordinary tables, unlike the free-text
 * documents that go through the RAG pipeline. No real patient data.
 */
class HospitalSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            Department::create([
                'name' => 'Cardiology',
                'description' => 'Diagnosis and treatment of heart and blood vessel conditions, including ECG, echocardiography and cardiac consultations.',
                'floor' => '3rd Floor',
                'building' => 'Building A',
                'phone' => '+1-555-0103',
                'visiting_hours' => '10:00 AM–12:00 PM and 5:00 PM–7:00 PM, daily',
                'is_emergency' => false,
            ]),
            Department::create([
                'name' => 'Emergency Medicine',
                'description' => 'Round-the-clock emergency and trauma care for critical and life-threatening conditions.',
                'floor' => 'Ground Floor',
                'building' => 'Building A',
                'phone' => '+1-555-0111',
                'visiting_hours' => 'Open 24 hours; visitor access is restricted while treatment is in progress.',
                'is_emergency' => true,
            ]),
            Department::create([
                'name' => 'Orthopedics',
                'description' => 'Treatment of bone, joint, and muscle conditions, including fractures, arthritis, and sports injuries.',
                'floor' => '2nd Floor',
                'building' => 'Building B',
                'phone' => '+1-555-0122',
                'visiting_hours' => '11:00 AM–1:00 PM and 4:00 PM–6:00 PM, daily',
                'is_emergency' => false,
            ]),
            Department::create([
                'name' => 'Pediatrics',
                'description' => 'Medical care for infants, children, and adolescents, including routine checkups and vaccinations.',
                'floor' => '4th Floor',
                'building' => 'Building A',
                'phone' => '+1-555-0134',
                'visiting_hours' => '10:00 AM–12:00 PM and 5:00 PM–6:30 PM, daily',
                'is_emergency' => false,
            ]),
            Department::create([
                'name' => 'General Medicine',
                'description' => 'Primary care for adults, covering common illnesses, chronic disease management, and referrals to specialists.',
                'floor' => '2nd Floor',
                'building' => 'Building A',
                'phone' => '+1-555-0145',
                'visiting_hours' => '10:00 AM–1:00 PM and 5:00 PM–7:00 PM, daily',
                'is_emergency' => false,
            ]),
            Department::create([
                'name' => 'Radiology',
                'description' => 'Diagnostic imaging services including X-ray, ultrasound, CT, and MRI scanning.',
                'floor' => '1st Floor',
                'building' => 'Building B',
                'phone' => '+1-555-0156',
                'visiting_hours' => 'By appointment, Monday–Saturday, 8:00 AM–6:00 PM',
                'is_emergency' => false,
            ]),
        ];

        [$cardiology, $emergency, $orthopedics, $pediatrics, $generalMedicine, $radiology] = $departments;

        $doctors = [
            [$cardiology, 'Dr. Anita Sharma', 'MD, DM (Cardiology)', 'Interventional Cardiology', ['Monday', 'Wednesday', 'Friday'], '09:00:00', '13:00:00', 'A-301'],
            [$cardiology, 'Dr. Rajiv Menon', 'MD (Cardiology)', 'Cardiac Electrophysiology', ['Tuesday', 'Thursday', 'Saturday'], '14:00:00', '18:00:00', 'A-304'],
            [$emergency, 'Dr. Kavita Rao', 'MBBS, MD (Emergency Medicine)', 'Emergency & Trauma Care', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], '08:00:00', '16:00:00', 'A-G12'],
            [$emergency, 'Dr. Sameer Joshi', 'MD (Emergency Medicine)', 'Critical Care', ['Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], '16:00:00', '23:00:00', 'A-G14'],
            [$orthopedics, 'Dr. Neha Kapoor', 'MS (Orthopedics)', 'Joint Replacement', ['Monday', 'Wednesday', 'Friday'], '11:00:00', '15:00:00', 'B-205'],
            [$orthopedics, 'Dr. Arjun Verma', 'MS (Orthopedics)', 'Sports Medicine', ['Tuesday', 'Thursday', 'Saturday'], '09:00:00', '13:00:00', 'B-208'],
            [$pediatrics, 'Dr. Priya Nair', 'MD (Pediatrics)', 'General Pediatrics', ['Monday', 'Tuesday', 'Thursday', 'Saturday'], '10:00:00', '14:00:00', 'A-402'],
            [$pediatrics, 'Dr. Rohan Gupta', 'MD (Pediatrics)', 'Neonatology', ['Wednesday', 'Friday', 'Saturday'], '09:00:00', '12:00:00', 'A-405'],
            [$generalMedicine, 'Dr. Meera Iyer', 'MD (Internal Medicine)', 'Internal Medicine', ['Monday', 'Tuesday', 'Wednesday', 'Friday'], '10:00:00', '13:00:00', 'A-210'],
            [$generalMedicine, 'Dr. Vikram Singh', 'MD (General Medicine)', 'Diabetes & Chronic Disease', ['Tuesday', 'Thursday', 'Saturday'], '15:00:00', '19:00:00', 'A-212'],
            [$radiology, 'Dr. Sunita Desai', 'MD (Radiology)', 'Diagnostic Imaging', ['Monday', 'Wednesday', 'Friday'], '08:00:00', '16:00:00', 'B-101'],
            [$radiology, 'Dr. Karan Malhotra', 'MD (Radiology)', 'MRI & CT Imaging', ['Tuesday', 'Thursday', 'Saturday'], '08:00:00', '16:00:00', 'B-104'],
        ];

        foreach ($doctors as [$department, $name, $qualification, $specialization, $days, $start, $end, $room]) {
            Doctor::create([
                'department_id' => $department->id,
                'name' => $name,
                'qualification' => $qualification,
                'specialization' => $specialization,
                'available_days' => $days,
                'consultation_start' => $start,
                'consultation_end' => $end,
                'room_number' => $room,
            ]);
        }

        $services = [
            [$generalMedicine, 'General OPD Consultation', 'Walk-in and scheduled consultations for common illnesses and general health concerns.', 'Mon–Sat, 9:00 AM–5:00 PM', false],
            [$emergency, 'Emergency & Trauma Care', 'Immediate care for critical, life-threatening, and accident-related conditions.', '24/7', false],
            [$cardiology, 'ECG & Echocardiography', 'Electrocardiogram and echocardiogram testing for heart function assessment.', 'Mon–Sat, 9:00 AM–4:00 PM', true],
            [$orthopedics, 'Orthopedic Physiotherapy', 'Rehabilitation and physical therapy for post-surgical and injury recovery.', 'Mon–Fri, 10:00 AM–5:00 PM', true],
            [$pediatrics, 'Child Vaccination', 'Routine and catch-up immunizations following the standard pediatric schedule.', 'Mon, Wed, Fri, 10:00 AM–1:00 PM', true],
            [$radiology, 'X-Ray Imaging', 'Diagnostic X-ray imaging for bones, chest, and abdomen.', 'Mon–Sat, 8:00 AM–6:00 PM', false],
            [$radiology, 'MRI Scan', 'Magnetic resonance imaging for detailed soft tissue and organ scans.', 'Mon–Sat, by appointment only', true],
            [null, 'Blood Test & Pathology Lab', 'Routine and specialized blood work, urinalysis, and lab diagnostics.', 'Mon–Sat, 7:00 AM–7:00 PM', false],
            [null, 'Ambulance Service', 'Emergency and non-emergency patient transport, available hospital-wide.', '24/7', false],
            [null, 'Annual Health Checkup Package', 'Comprehensive preventive health screening covering blood work, ECG, and consultation.', 'Mon–Sat, by appointment', true],
        ];

        foreach ($services as [$department, $name, $description, $availability, $requiresAppointment]) {
            Service::create([
                'department_id' => $department?->id,
                'name' => $name,
                'description' => $description,
                'availability' => $availability,
                'requires_appointment' => $requiresAppointment,
            ]);
        }
    }
}
