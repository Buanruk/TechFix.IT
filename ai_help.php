<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI ช่วยวิเคราะห์ปัญหา - TechFix</title>
    <style>
        /* CSS ตัวอย่างเล็กน้อย */
        body { font-family: sans-serif; margin: 20px; }
        #ai-container { max-width: 600px; margin: auto; }
        #problem-input { width: 100%; padding: 10px; box-sizing: border-box; }
        #ai-response { 
            margin-top: 20px; 
            border: 1px solid #ddd; 
            background: #f9f9f9; 
            padding: 15px; 
            min-height: 100px; 
            white-space: pre-wrap; /* ทำให้แสดงผลขึ้นบรรทัดใหม่ตามที่ AI ส่งมา */
        }
        #loading { display: none; color: #888; }
    </style>
</head>
<body>

    <div id="ai-container">
        <h2>ระบบ AI ช่วยวิเคราะห์ปัญหา</h2>
        <p>กรุณาอธิบายอาการเสียของอุปกรณ์ (เช่น "โน้ตบุ๊กเปิดไม่ติด", "ปริ้นเตอร์ไม่ออกสี")</p>

        <form id="ai-form">
            <textarea id="problem-input" rows="4" placeholder="อธิบายปัญหาของคุณที่นี่..."></textarea>
            <br><br>
            <button type="submit">ส่งให้ AI วิเคราะห์</button>
        </form>

        <p id="loading">กำลังประมวลผล โปรดรอสักครู่...</p>
        <div id="ai-response">
            (คำแนะนำจาก AI จะแสดงที่นี่)
        </div>
    </div>

    <script>
        // เลือก element ที่เราจะใช้งาน
        const aiForm = document.getElementById('ai-form');
        const problemInput = document.getElementById('problem-input');
        const aiResponse = document.getElementById('ai-response');
        const loading = document.getElementById('loading');

        // เมื่อฟอร์มถูกกด "ส่ง"
        aiForm.addEventListener('submit', async function(event) {
            // ป้องกันไม่ให้หน้าเว็บโหลดใหม่
            event.preventDefault(); 

            const userMessage = problemInput.value;
            if (!userMessage) {
                alert('กรุณาพิมพ์ปัญหาก่อนครับ');
                return;
            }

            // แสดงสถานะ "กำลังโหลด"
            loading.style.display = 'block';
            aiResponse.innerHTML = ''; // ล้างคำตอบเก่า

            try {
                // ส่ง "message" ไปยังไฟล์ ai_rulebased.php
                const response = await fetch('ai_rulebased.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message: userMessage })
                });

                // รับคำตอบ (JSON) กลับมา
                const data = await response.json();
                
                // แสดงคำตอบในช่อง ai-response
                aiResponse.innerHTML = data.reply;

            } catch (error) {
                // หากเกิดข้อผิดพลาด
                aiResponse.innerHTML = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error.message;
            } finally {
                // ซ่อนสถานะ "กำลังโหลด"
                loading.style.display = 'none';
            }
        });
    </script>

</body>
</html>