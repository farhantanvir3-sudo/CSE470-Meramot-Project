"""
Meramot AI Diagnostic & Summary Microservice
CSE 470: Software Engineering (Section 06) — Group 6
Features: R10 (AI Diagnostic Chatbot) & R13 (Hardware Fault Summarizer)
Assigned Lead: Farhan Tanvir [22299179]
"""

import json
import os
import re
from typing import List, Optional
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv

load_dotenv()

app = FastAPI(
    title="Meramot AI Diagnostic Microservice",
    description="Dedicated FastAPI microservice providing device diagnostic analysis and request summarization for repair vendors.",
    version="2.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


class SummarizeRequest(BaseModel):
    device_type: Optional[str] = "Electronic Device"
    brand: Optional[str] = "Generic"
    model_name: Optional[str] = "Device"
    issue_title: Optional[str] = "Hardware Fault"
    issue_description: Optional[str] = ""
    issue_category: Optional[str] = ""


class SummarizeResponse(BaseModel):
    status: str
    provider: str = "fastapi-heuristic"
    model_name: str = "meramot-hardware-triage-v2"
    summary: str
    suspected_faults: List[str]
    suggested_parts: List[str]
    risk_level: str = "MEDIUM"
    estimated_bench_time_hours: float
    estimated_cost_min: float = 800.0
    estimated_cost_max: float = 3500.0


class DiagnosticChatRequest(BaseModel):
    session_id: Optional[str] = "session-default"
    device_category: str
    user_message: str


class DiagnosticChatResponse(BaseModel):
    status: str
    reply: str
    is_repair_booking_recommended: bool
    possible_causes: List[str]


def _is_real_key(val: Optional[str]) -> bool:
    if not val:
        return False
    cleaned = val.strip()
    if not cleaned or cleaned.startswith("your_") or cleaned.lower() in {"placeholder", "none", "test"}:
        return False
    return len(cleaned) >= 16


def _heuristic_triage(payload: SummarizeRequest) -> SummarizeResponse:
    text = f"{payload.issue_title or ''} {payload.issue_description or ''} {payload.issue_category or ''}".lower()
    device = (payload.device_type or "Device").strip()
    brand = (payload.brand or "Brand").strip()
    model = (payload.model_name or "Model").strip()

    suspected: List[str] = []
    parts: List[str] = []
    bench_hours = 1.5
    risk = "LOW"
    cost_min = 900.0
    cost_max = 2800.0

    rules = [
        (
            ["screen", "display", "oled", "lcd", "crack", "shatter", "flicker", "green line", "touch", "digitizer", "black screen"],
            "Display Panel / Digitizer Flex or Backlight Driver Degradation",
            "OEM OLED/IPS Display & Digitizer Assembly + Frame Adhesive Seal",
            2.0, "MEDIUM", 2200.0, 8500.0,
        ),
        (
            ["battery", "drain", "swollen", "bulge", "shut down", "percentage", "fast drain"],
            "Lithium-Polymer Cell Cycle Exhaustion / High Internal Impedance",
            "Zero-Cycle Replacement Battery Pack + BMS Calibration",
            1.2, "LOW", 1200.0, 4200.0,
        ),
        (
            ["charg", "port", "usb", "type-c", "lightning", "not charging", "slow charg", "cable"],
            "Charging Daughterboard Wear / USB-C PD Controller (Tristar/PMIC) Fault",
            "Charging Port Flex Assembly / USB-C PD Controller IC",
            1.8, "MEDIUM", 950.0, 3200.0,
        ),
        (
            ["water", "liquid", "rain", "spill", "tea", "coffee", "corrosion", "submerg", "wet"],
            "Multi-Rail PCB Liquid Ingress, Electrolytic Trace Corrosion & Short",
            "Ultrasonic Isopropyl Bath + Corroded SMD Capacitor/Resistor Array",
            4.0, "HIGH", 2500.0, 7500.0,
        ),
        (
            ["dead", "no power", "won't turn on", "not turning on", "short", "mosfet", "motherboard", "logic board", "vrm"],
            "Primary Power Rail (PPVDD_MAIN / Vcore) Short or PMIC Failure",
            "DC-DC Buck MOSFET / Power Management IC + Micro-Soldering Rework",
            4.5, "HIGH", 3000.0, 9500.0,
        ),
        (
            ["overheat", "thermal", "fan", "loud", "throttl", "hot"],
            "Heatsink Fin Obstruction, Dried TIM Paste, or Cooling Fan Bearing Wear",
            "High-Conductivity Thermal Paste/Pads + Cooling Fan Module",
            1.5, "LOW", 800.0, 2600.0,
        ),
        (
            ["keyboard", "key", "trackpad", "touchpad", "typing", "sticky"],
            "Keyboard Membrane Matrix Oxidation / Trackpad Ribbon Fault",
            "Palmrest/Scissor-Switch Keyboard Assembly + Trackpad Flex",
            2.2, "MEDIUM", 1800.0, 5200.0,
        ),
        (
            ["hinge", "frame", "back glass", "housing", "broken body", "lid"],
            "Chassis Standoff Fracture & Torque Hinge Seizure",
            "Left/Right Torque Hinge Pair + Brass Insert Epoxy Reinforcement",
            2.5, "MEDIUM", 1600.0, 4500.0,
        ),
        (
            ["speaker", "mic", "audio", "sound", "earpiece", "no voice", "crackling"],
            "Acoustic Transducer Torn Diaphragm or Audio Codec IC Cold Joint",
            "Earpiece/Loudspeaker Module + MEMS Microphone Flex",
            1.5, "LOW", 850.0, 2900.0,
        ),
        (
            ["camera", "blur", "focus", "lens", "face id", "ois", "front cam"],
            "Camera OIS Actuator Failure / Cracked Sapphire Lens Cover",
            "Rear/Front Camera Sensor Module + Optical Lens Glass",
            1.5, "MEDIUM", 1500.0, 6200.0,
        ),
        (
            ["ssd", "hard drive", "boot", "bios", "blue screen", "bsod", "stuck on logo", "bootloop", "os"],
            "NAND Storage Bad Sectors / Bootloader Firmware or RAM Instability",
            "NVMe SSD Replacement / SPI BIOS Re-Flash & OS Recovery",
            2.5, "MEDIUM", 1200.0, 5800.0,
        ),
        (
            ["hdmi", "port", "no signal", "console", "ps5", "xbox", "gpu", "artifact"],
            "HDMI 2.1 Port Pin Shear / Display Retimer IC Failure",
            "HDMI 2.1 Connector Socket + Display Retimer IC",
            3.0, "HIGH", 2200.0, 6500.0,
        ),
    ]

    matched_max_hours = 0.0
    total_min = 0.0
    total_max = 0.0
    risk_rank = {"LOW": 1, "MEDIUM": 2, "HIGH": 3}
    highest_risk = "LOW"

    for keywords, fault_label, part_label, hrs, r_level, c_min, c_max in rules:
        if any(k in text for k in keywords):
            suspected.append(fault_label)
            parts.append(part_label)
            matched_max_hours = max(matched_max_hours, hrs)
            total_min += c_min
            total_max += c_max
            if risk_rank[r_level] > risk_rank[highest_risk]:
                highest_risk = r_level

    if not suspected:
        suspected.append("Intermittent Board-Level Component Fatigue / Connector Oxidation")
        parts.append("Bench Multimeter & Thermal Camera Diagnostic + Flex Reseat")
        bench_hours = 2.0
        risk = "MEDIUM"
        cost_min = 800.0
        cost_max = 3000.0
    else:
        bench_hours = round(min(matched_max_hours + 0.5 * (len(suspected) - 1), 8.0), 1)
        risk = highest_risk
        cost_min = round(total_min * 0.85, -1)
        cost_max = round(total_max, -1)

    summary = (
        f"{brand} {model} ({device}) reported with '{payload.issue_title}'. "
        f"Automated bench triage flags {len(suspected)} primary fault vector(s): {'; '.join(suspected[:2])}. "
        f"Estimated bench diagnostic & rework window: ~{bench_hours} hrs ({risk} complexity)."
    )

    return SummarizeResponse(
        status="success",
        provider="fastapi-heuristic",
        model_name="meramot-hardware-triage-v2",
        summary=summary,
        suspected_faults=suspected,
        suggested_parts=parts,
        risk_level=risk,
        estimated_bench_time_hours=bench_hours,
        estimated_cost_min=cost_min,
        estimated_cost_max=cost_max,
    )


def _try_llm_triage(payload: SummarizeRequest) -> Optional[SummarizeResponse]:
    groq_key = os.getenv("GROQ_API_KEY", "")
    gemini_key = os.getenv("GEMINI_API_KEY", "")
    primary = os.getenv("PRIMARY_AI_PROVIDER", "groq").lower()

    prompt = (
        "You are a senior electronics micro-soldering & repair triage engineer in Bangladesh. "
        "Return STRICT JSON ONLY with keys: summary (2 concise sentences), suspected_faults (array of 2-3 strings), "
        "suggested_parts (array of 1-3 strings), risk_level (LOW|MEDIUM|HIGH), estimated_bench_time_hours (number), "
        "estimated_cost_min (BDT number), estimated_cost_max (BDT number).\n"
        f"Device: {payload.brand} {payload.model_name} ({payload.device_type})\n"
        f"Issue Title: {payload.issue_title}\n"
        f"Symptom Details: {payload.issue_description}"
    )

    providers = ["groq", "gemini"] if primary == "groq" else ["gemini", "groq"]

    for p in providers:
        try:
            if p == "groq" and _is_real_key(groq_key):
                from groq import Groq
                client = Groq(api_key=groq_key.strip())
                completion = client.chat.completions.create(
                    model="llama-3.3-70b-versatile",
                    messages=[{"role": "user", "content": prompt}],
                    temperature=0.2,
                    response_format={"type": "json_object"},
                )
                raw = completion.choices[0].message.content or "{}"
                data = json.loads(raw)
                return SummarizeResponse(
                    status="success",
                    provider="groq",
                    model_name="llama-3.3-70b-versatile",
                    summary=str(data.get("summary", "")).strip() or _heuristic_triage(payload).summary,
                    suspected_faults=list(data.get("suspected_faults") or ["Hardware fault requires bench check"]),
                    suggested_parts=list(data.get("suggested_parts") or ["Diagnostic inspection"]),
                    risk_level=str(data.get("risk_level", "MEDIUM")).upper(),
                    estimated_bench_time_hours=float(data.get("estimated_bench_time_hours", 2.0)),
                    estimated_cost_min=float(data.get("estimated_cost_min", 1000.0)),
                    estimated_cost_max=float(data.get("estimated_cost_max", 4000.0)),
                )
            if p == "gemini" and _is_real_key(gemini_key):
                from google import genai
                client = genai.Client(api_key=gemini_key.strip())
                resp = client.models.generate_content(
                    model="gemini-2.5-flash",
                    contents=prompt,
                )
                raw = (resp.text or "").strip()
                raw = re.sub(r"^```(?:json)?|```$", "", raw, flags=re.MULTILINE).strip()
                data = json.loads(raw)
                return SummarizeResponse(
                    status="success",
                    provider="gemini",
                    model_name="gemini-2.5-flash",
                    summary=str(data.get("summary", "")).strip() or _heuristic_triage(payload).summary,
                    suspected_faults=list(data.get("suspected_faults") or ["Hardware fault requires bench check"]),
                    suggested_parts=list(data.get("suggested_parts") or ["Diagnostic inspection"]),
                    risk_level=str(data.get("risk_level", "MEDIUM")).upper(),
                    estimated_bench_time_hours=float(data.get("estimated_bench_time_hours", 2.0)),
                    estimated_cost_min=float(data.get("estimated_cost_min", 1000.0)),
                    estimated_cost_max=float(data.get("estimated_cost_max", 4000.0)),
                )
        except Exception:
            continue

    return None


@app.get("/")
def health_check():
    return {
        "service": "Meramot AI Microservice",
        "status": "operational",
        "course": "CSE 470 Software Engineering",
        "lead_developer": "Farhan Tanvir [22299179]",
        "groq_configured": _is_real_key(os.getenv("GROQ_API_KEY")),
        "gemini_configured": _is_real_key(os.getenv("GEMINI_API_KEY")),
        "supported_features": ["R10 (Diagnostic Chat)", "R13 (Fault Summarization)"],
    }


@app.post("/api/ai/summarize-request", response_model=SummarizeResponse)
def summarize_repair_request(payload: SummarizeRequest):
    """
    Feature R13: FastAPI GROQ/Gemini AI Request Summary Microservice
    Uses Groq / Gemini LLM when keys are configured, with deterministic hardware-engineering fallback.
    """
    llm_result = _try_llm_triage(payload)
    if llm_result is not None:
        return llm_result
    return _heuristic_triage(payload)


@app.post("/api/ai/chat", response_model=DiagnosticChatResponse)
def diagnostic_chat(payload: DiagnosticChatRequest):
    """
    Feature R10: AI-Powered Repair Diagnostic Conversational Consultant
    """
    user_query = payload.user_message.strip()
    triage = _heuristic_triage(
        SummarizeRequest(
            device_type=payload.device_category,
            brand=payload.device_category,
            model_name="Device",
            issue_title=user_query[:80],
            issue_description=user_query,
        )
    )

    reply = (
        f"For your {payload.device_category} ('{user_query}'): initial symptom analysis points toward "
        f"{', '.join(triage.suspected_faults[:2])}. "
        f"Estimated workshop time is ~{triage.estimated_bench_time_hours} hrs (typical range BDT {int(triage.estimated_cost_min)}–{int(triage.estimated_cost_max)}). "
        f"We recommend avoiding forced charging if heat or liquid is involved and posting a Repair Request so verified technicians can bid."
    )

    return DiagnosticChatResponse(
        status="success",
        reply=reply,
        is_repair_booking_recommended=True,
        possible_causes=triage.suspected_faults,
    )


if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("PORT", "5000"))
    host = os.getenv("HOST", "127.0.0.1")
    uvicorn.run("main:app", host=host, port=port, reload=True)
