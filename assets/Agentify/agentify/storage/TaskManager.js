import { StorageError } from '../errors/ErrorTypes.js';
import { formatBytes, formatRelativeTime } from '../utils/formatters.js';

/**
 * Manages task persistence using browser localStorage
 */
export class TaskManager {
  constructor(storageKey = 'agentify_tasks', errorManager) {
    this.storageKey = storageKey;
    this.errorManager = errorManager;
    this.maxTasks = 1000;
    this.compressionThreshold = 500;
    
    this.ensureStorageAvailable();
  }

  /**
   * Ensure localStorage is available
   */
  ensureStorageAvailable() {
    try {
      const test = '__agentify_test__';
      localStorage.setItem(test, test);
      localStorage.removeItem(test);
    } catch (error) {
      throw this.errorManager.createStorageError(
        'localStorage is not available',
        StorageError.codes.NOT_AVAILABLE,
        { originalError: error.message }
      );
    }
  }

  /**
   * Generate unique task ID
   */
  generateTaskId() {
    return `task_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Create a new task (model-created tasks with title, description, status, chat_id)
   */
  createTask(task) {
    try {
      if (!task.title || typeof task.title !== 'string' || !task.title.trim()) {
        throw this.errorManager.createSystemError(
          'Task title is required',
          'SYS_INVALID_PARAMETER',
          {}
        );
      }

      const taskId = task.id || this.generateTaskId();
      const now = new Date().toISOString();
      
      const taskData = {
        id: taskId,
        title: String(task.title).trim(),
        description: task.description ? String(task.description).trim() : null,
        status: task.status === 'completed' ? 'completed' : 'pending',
        chat_id: task.chat_id || null,
        created_at: task.created_at || now,
        updated_at: now
      };

      const tasks = this.getTasks();
      tasks.push(taskData);

      // Trim if exceeds max
      if (tasks.length > this.maxTasks) {
        tasks.splice(0, tasks.length - this.maxTasks);
      }

      this.saveTasks(tasks);
      
      return taskData;
    } catch (error) {
      if (error instanceof StorageError || error.name === 'SystemError') {
        throw error;
      }
      throw this.errorManager.createStorageError(
        'Failed to create task',
        StorageError.codes.WRITE_FAILED,
        { originalError: error.message }
      );
    }
  }

  /**
   * Add a new task (legacy method - kept for backward compatibility, but use createTask instead)
   * @deprecated Use createTask() instead
   */
  addTask(task) {
    return this.createTask({
      title: task.input || task.type || 'Task',
      description: task.output || null,
      status: task.status || 'pending',
      chat_id: task.chat_id || null
    });
  }

  /**
   * Update task status (pending or completed)
   */
  updateTaskStatus(taskId, status, data = {}) {
    try {
      if (status !== 'pending' && status !== 'completed') {
        throw this.errorManager.createSystemError(
          'Task status must be "pending" or "completed"',
          'SYS_INVALID_PARAMETER',
          { taskId, status }
        );
      }

      const tasks = this.getTasks();
      const taskIndex = tasks.findIndex(t => t.id === taskId);

      if (taskIndex === -1) {
        throw this.errorManager.createStorageError(
          `Task not found: ${taskId}`,
          StorageError.codes.READ_FAILED,
          { taskId }
        );
      }

      const now = new Date().toISOString();
      tasks[taskIndex] = {
        ...tasks[taskIndex],
        status,
        ...data,
        updated_at: now
      };

      this.saveTasks(tasks);
      
      return tasks[taskIndex];
    } catch (error) {
      if (error instanceof StorageError || error.name === 'SystemError') {
        throw error;
      }
      throw this.errorManager.createStorageError(
        'Failed to update task status',
        StorageError.codes.WRITE_FAILED,
        { taskId, status, originalError: error.message }
      );
    }
  }

  /**
   * Get tasks with optional filtering (by chat_id, status, limit)
   */
  getTasks(filter = {}) {
    try {
      const tasksJson = localStorage.getItem(this.storageKey);
      
      if (!tasksJson) {
        return [];
      }

      let tasks = JSON.parse(tasksJson);

      // Apply filters
      if (filter.chat_id) {
        tasks = tasks.filter(t => t.chat_id === filter.chat_id);
      }

      if (filter.status) {
        tasks = tasks.filter(t => t.status === filter.status);
      }

      if (filter.since) {
        const sinceDate = new Date(filter.since);
        tasks = tasks.filter(t => {
          const taskDate = t.created_at || t.timestamp || t.updated_at;
          return taskDate && new Date(taskDate) >= sinceDate;
        });
      }

      if (filter.limit) {
        tasks = tasks.slice(-filter.limit);
      }

      // Sort by created_at descending (newest first)
      tasks.sort((a, b) => {
        const dateA = new Date(a.created_at || a.timestamp || 0);
        const dateB = new Date(b.created_at || b.timestamp || 0);
        return dateB - dateA;
      });

      return tasks;
    } catch (error) {
      throw this.errorManager.createStorageError(
        'Failed to read tasks',
        StorageError.codes.READ_FAILED,
        { filter, originalError: error.message }
      );
    }
  }

  /**
   * Get single task by ID
   */
  getTask(taskId) {
    const tasks = this.getTasks();
    const task = tasks.find(t => t.id === taskId);

    if (!task) {
      throw this.errorManager.createStorageError(
        `Task not found: ${taskId}`,
        StorageError.codes.READ_FAILED,
        { taskId }
      );
    }

    return task;
  }

  /**
   * Save tasks to localStorage
   */
  saveTasks(tasks) {
    try {
      const tasksJson = JSON.stringify(tasks);
      localStorage.setItem(this.storageKey, tasksJson);
    } catch (error) {
      if (error.name === 'QuotaExceededError') {
        // Try to compress old tasks
        this.compressOldTasks(tasks);
        
        try {
          const tasksJson = JSON.stringify(tasks);
          localStorage.setItem(this.storageKey, tasksJson);
        } catch {
          throw this.errorManager.createStorageError(
            'Storage quota exceeded even after compression',
            StorageError.codes.QUOTA_EXCEEDED,
            {
              taskCount: tasks.length,
              storageUsed: this.getStorageUsage()
            }
          );
        }
      } else {
        throw this.errorManager.createStorageError(
          'Failed to save tasks',
          StorageError.codes.WRITE_FAILED,
          { originalError: error.message }
        );
      }
    }
  }

  /**
   * Compress old tasks to save space
   */
  compressOldTasks(tasks) {
    if (tasks.length <= this.compressionThreshold) {
      return;
    }

    const keepCount = Math.floor(this.compressionThreshold / 2);
    const oldTasks = tasks.slice(0, tasks.length - keepCount);

    // Remove output/input from old tasks
    oldTasks.forEach(task => {
      if (task.output && typeof task.output === 'string') {
        task.output = task.output.substring(0, 100) + '... [compressed]';
      }
      if (task.input && typeof task.input === 'string') {
        task.input = task.input.substring(0, 100) + '... [compressed]';
      }
    });

    tasks.splice(0, oldTasks.length, ...oldTasks);
  }

  /**
   * Export tasks in specified format
   */
  exportTasks(format = 'json') {
    const tasks = this.getTasks();

    switch (format.toLowerCase()) {
      case 'json':
        return JSON.stringify(tasks, null, 2);
      
      case 'csv':
        return this.tasksToCSV(tasks);
      
      case 'text':
        return this.tasksToText(tasks);
      
      default:
        return JSON.stringify(tasks);
    }
  }

  /**
   * Convert tasks to CSV format
   */
  tasksToCSV(tasks) {
    if (tasks.length === 0) {
      return 'id,title,description,status,chat_id,created_at,updated_at';
    }

    const headers = 'id,title,description,status,chat_id,created_at,updated_at';
    const rows = tasks.map(task => {
      return [
        task.id,
        (task.title || '').replace(/"/g, '""'),
        (task.description || '').replace(/"/g, '""'),
        task.status || 'pending',
        task.chat_id || '',
        task.created_at || task.timestamp || '',
        task.updated_at || ''
      ].map(v => `"${v}"`).join(',');
    });

    return [headers, ...rows].join('\n');
  }

  /**
   * Convert tasks to text format
   */
  tasksToText(tasks) {
    return tasks.map(task => {
      const createdAt = task.created_at || task.timestamp || '';
      const updatedAt = task.updated_at || createdAt;
      return `
Task: ${task.id}
Title: ${task.title || 'N/A'}
Description: ${task.description || 'N/A'}
Status: ${task.status || 'pending'}
Chat ID: ${task.chat_id || 'N/A'}
Created: ${createdAt}${createdAt ? ` (${formatRelativeTime(createdAt)})` : ''}
Updated: ${updatedAt}${updatedAt && updatedAt !== createdAt ? ` (${formatRelativeTime(updatedAt)})` : ''}
${'='.repeat(60)}
      `.trim();
    }).join('\n\n');
  }

  /**
   * Clear all tasks
   */
  clearTasks() {
    try {
      localStorage.removeItem(this.storageKey);
      return true;
    } catch (error) {
      throw this.errorManager.createStorageError(
        'Failed to clear tasks',
        StorageError.codes.WRITE_FAILED,
        { originalError: error.message }
      );
    }
  }

  /**
   * Delete specific task
   */
  deleteTask(taskId) {
    const tasks = this.getTasks();
    const filteredTasks = tasks.filter(t => t.id !== taskId);
    
    if (tasks.length === filteredTasks.length) {
      return false; // Task not found
    }

    this.saveTasks(filteredTasks);
    return true;
  }

  /**
   * Get storage statistics
   */
  getStorageStats() {
    const tasks = this.getTasks();
    const tasksJson = JSON.stringify(tasks);
    
    const statusCounts = tasks.reduce((acc, task) => {
      const status = task.status || 'pending';
      acc[status] = (acc[status] || 0) + 1;
      return acc;
    }, {});

    const chatIdCounts = tasks.reduce((acc, task) => {
      const chatId = task.chat_id || 'none';
      acc[chatId] = (acc[chatId] || 0) + 1;
      return acc;
    }, {});

    const getTaskDate = (task) => task.created_at || task.timestamp || task.updated_at || null;
    const sortedByDate = [...tasks].sort((a, b) => {
      const dateA = getTaskDate(a);
      const dateB = getTaskDate(b);
      if (!dateA && !dateB) return 0;
      if (!dateA) return 1;
      if (!dateB) return -1;
      return new Date(dateA) - new Date(dateB);
    });

    return {
      totalTasks: tasks.length,
      storageUsed: tasksJson.length,
      storageUsedFormatted: formatBytes(tasksJson.length),
      statusCounts,
      byStatus: statusCounts,
      chatIdCounts,
      oldestTask: sortedByDate.length > 0 ? getTaskDate(sortedByDate[0]) : null,
      newestTask: sortedByDate.length > 0 ? getTaskDate(sortedByDate[sortedByDate.length - 1]) : null
    };
  }

  /**
   * Get storage usage
   */
  getStorageUsage() {
    const tasksJson = localStorage.getItem(this.storageKey) || '';
    return {
      bytes: tasksJson.length,
      formatted: formatBytes(tasksJson.length)
    };
  }

  /**
   * Get task count
   */
  getTaskCount() {
    return this.getTasks().length;
  }
}

export default TaskManager;
