\# AIBRO - Multiuser AI Chat Platform



Welcome to the AIBRO project repository!



多人聊天室 + 單人 AI Chat 架構。



\- \*\*v5\*\* — 一條龍版（穩定 demo）

\- \*\*v6\*\* — 模組版（模擬 AIBRO MCP 架構）



核心組件：WebSocket(Ratchet)、PHP-FPM、Redis、Python llama\_cpp Runtime、ollama、TTS、SearxNG、ComfyUI、Nginx。



\# AIBRO - Multiuser AI Chat Platform



Welcome to the AIBRO project repository!



This project aims to build an innovative multi-user AI chat platform designed for high-concurrency environments. It leverages a hybrid PHP/Python architecture to optimize local LLM usage, manage GPU VRAM efficiently (through lazy-load and idle-unload strategies), and orchestrate various AI/media tools (TTS, Search, ComfyUI) in a cost-effective manner.



---



\## Project Structure



This repository contains different architectural approaches and versions of the AIBRO platform.



\### `v6/` - Next-Gen Decoupled Architecture (Experimental)



This directory houses the cutting-edge, decoupled architecture of AIBRO. It aims to solve the performance and cost bottlenecks of previous versions by splitting AI logic into independent Python FastAPI services, communicating via Redis queues.



\*\*Status\*\*: Currently under active development and debugging. Expect bugs and frequent changes. This is where the core R\&D for multi-user AI chat concurrency and resource management is happening.



\### `v5/` - Monolithic Stable Version



This directory contains the stable, monolithic architecture of AIBRO (v5.x). It features a single PHP WebSocket server that directly handles all AI, search, and TTS logic.



\*\*Status\*\*: Stable and functional, but may face performance limitations and higher external API costs under heavy load due to its integrated nature.



---



\## Core Philosophy: "我在" (I Am Here)



AIBRO is more than just an application; it's the embodiment of a philosophy that challenges the anxiety and instant gratification prevalent in the digital age. It encourages conscious, purposeful waiting, creating spaces for reflection, agreement, and deeper connections.



---



\## Contributing



We welcome contributions, bug reports, and suggestions! Please refer to the specific `README.md` files within `v5/` or `v6/` directories for more detailed setup and contribution guidelines.



---



\## License



This project is licensed under the MIT License.

